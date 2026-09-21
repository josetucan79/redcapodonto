<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
// Check if any notices need to be displayed regarding Draft Mode
include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";

loadCSS("OnlineDesigner.css");
addLangToJS([
	"draft_preview_02",
	"draft_preview_20",
	"draft_preview_21",
	"draft_preview_03",
	"draft_preview_05",
	"draft_preview_17",
	"draft_preview_18",
]);

// Video link
print 	RCView::div(array('class'=>'clearfix ms-2 mt-2 mb-3'),
			'<i class="fas fa-film"></i> ' .
			RCView::a(array('href'=>'javascript:;','style'=>'vertical-align:middle;font-size:12px;text-decoration:underline;font-weight:normal;','onclick'=>"window.open('".CONSORTIUM_WEBSITE."videoplayer.php?video=intro_instrument_dev.mp4&referer=".SERVER_NAME."&title=".js_escape($lang['training_res_101'])."','myWin','width=1050, height=800, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');"), $lang['design_02'])
		);


// If data dictionary upload was successful, give user a confirmation
if (isset($_GET['upload_success']))
{
	// Give user confirmation of successful changes to project
	print  "<p></p>
			<div class='green' style='margin:30px 0;padding-bottom:15px;'>
				<img src='".APP_PATH_IMAGES."accept.png'>
				<b>" . ($status == 0 ? $lang['design_03'] : $lang['design_730']) . "</b><br><br>
				{$lang['design_04']}
				" . ($status == 0 ? "" : "<br><br>
										  <b>" . $lang['design_05'] . "</b> ".$lang['design_167']."
										  <a style='text-decoration:underline;font-family:verdana;' href='".APP_PATH_WEBROOT."Design/project_modifications.php?pid=$project_id&ref='+page>".
										  $lang['design_18']."</a> ".$lang['design_168'])
				. "
			</div>";
	// Link to return to previous page
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit;
}




// Instructions (but not while editing a form)
if (!isset($_GET['page']))
{
	print  "<p>{$lang['design_07']}</p>";
}



//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0) ? "redcap_metadata_temp" : "redcap_metadata";




/**
 * UPLOAD DATA DICTIONARY
 */

#Set official upload directory
$upload_dir = APP_PATH_TEMP;
if (!is_writeable($upload_dir)) {
	print "<br><br><div class='red'>
		<img src='".APP_PATH_IMAGES."exclamation.png'> <b>{$lang['global_01']}:</b><br>
		{$lang['design_106']} <b>$upload_dir</b> {$lang['design_107']}</div>";
	include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
	exit();
}

# Define the sheet name. Here it automatically give it the name of the file, but is limited to the first 31 characters. Use if as default.
$importsheetname = 'Sheet1';

// First page
if (!isset($_POST['submit']) && !isset($_POST['commit']))
{
    // Set the drop-down download button options
    $dropdownDownloadDDBtn = '<div class="btn-group nowrap">    
                                <button class="btn btn-sm btn-defaultrc fs13" onclick="downloadDD(0,'.$Proj->formsFromLibrary().');" tabindex="0">
                                    <img src="'.APP_PATH_IMAGES.'xls.gif" style="top: -1px; position: relative;"> '.RCView::tt('data_import_tool_395').'</span>
                                </button>
                                <button
                                    id="download-btn-dropdown"
                                    title="'.RCView::tt_js2('data_import_tool_396').'"
                                    class="btn btn-sm btn-defaultrc fs13 dropdown-toggle"
                                    tabindex="0"
                                    data-toggle="dropdown"
                                    aria-haspopup="true"
                                    aria-expanded="false"
                                    data-bs-toggle="dropdown"
                                >
                                    </button>
                                    <div class="dropdown-menu dropdown-menu-end">
                                        <div style="font-weight: bold">
                                            <a class="dropdown-item" href="javascript:;" onclick="downloadDD(0,'.$Proj->formsFromLibrary().');">'.RCView::tt('data_import_tool_397').' '.RCView::tt('data_import_tool_400').'</a>
                                        </div>
                                        <div style="padding-left: 10px;">
                                            <a class="dropdown-item" href="javascript:;" onclick="downloadDD(0,'.$Proj->formsFromLibrary().',\',\');">'.RCView::tt('data_import_tool_398').' '.RCView::tt('global_162').'</a>
                                            <a class="dropdown-item" href="javascript:;" onclick="downloadDD(0,'.$Proj->formsFromLibrary().',\'tab\');">'.RCView::tt('data_import_tool_398').' '.RCView::tt('global_163').'</a>
                                            <a class="dropdown-item" href="javascript:;" onclick="downloadDD(0,'.$Proj->formsFromLibrary().',\';\');">'.RCView::tt('data_import_tool_398').' '.RCView::tt('global_164').'</a>
                                        </div>';
    if ($status > 0 && $draft_mode > 0) {
        $dropdownDownloadDDBtn .= '<div>
                                    <hr style="border: none;border-bottom: 1px dashed #000; margin-top:1%; margin-bottom:1%; width: 100%;" />
                                </div>
                                <div>
                                    <a class="dropdown-item" href="javascript:;" onclick="downloadDD(1,'.$Proj->formsFromLibrary().');">'.RCView::tt('data_import_tool_399').' '.RCView::tt('data_import_tool_400').'</a>
                                </div>
                                <div style="padding-left: 10px;">
                                    <a class="dropdown-item" href="javascript:;" onclick="downloadDD(1,'.$Proj->formsFromLibrary().',\',\');">'.RCView::tt('data_import_tool_398').' '.RCView::tt('global_162').'</a>
                                    <a class="dropdown-item" href="javascript:;" onclick="downloadDD(1,'.$Proj->formsFromLibrary().',\'tab\');">'.RCView::tt('data_import_tool_398').' '.RCView::tt('global_163').'</a>
                                    <a class="dropdown-item" href="javascript:;" onclick="downloadDD(1,'.$Proj->formsFromLibrary().',\';\');">'.RCView::tt('data_import_tool_398').' '.RCView::tt('global_164').'</a>
                                </div>';
    }

    $dropdownDownloadDDBtn .= '</div></div>';

    $uploadFormHTML = '';
    if ($draft_mode == 0 && $status > 0)
    {
        // If in production but not in Draft Mode yet, give notice that cannot yet upload DD
        $uploadFormHTML .= RCView::div(['class'=>'round', 'style'=>'background-color:#EFF6E8;max-width:700px;margin:20px 0;padding:15px 25px;border:1px solid #A5CC7A;'],
                        RCView::b($lang['global_03'].$lang['colon']). RCView::SP . $lang['design_370']
        );
    }
    elseif ($draft_mode == 2 && $status > 0)
    {
        // If in production and drafted changes are awaiting review by admin
        $uploadFormHTML .= RCView::div(['class'=>'round', 'style'=>'background-color:#EFF6E8;max-width:700px;margin:20px 0;padding:15px 25px;border:1px solid #A5CC7A;'],
            RCView::b($lang['global_03'].$lang['colon']). RCView::SP . $lang['design_632']
        );
    }
    else
    {
        $uploadFormHTML .= RCView::div(['style'=>'padding-bottom: 10px;'],
            RCView::span(['style'=>'color: #800000;'], RCView::tt('design_110')). " ".
            RCView::span([''], RCView::tt('design_111')." ". RCView::tt('design_259')) .
            RCView::div(['class'=>'mt-2', 'style'=>'line-height:1.2;'],
                RCView::b(['style'=>'font-size: 12px;'], RCView::tt('design_692')) . " ".
                RCView::span(['style'=>'color: #800000; font-size: 12px;'], RCView::tt('design_691'))
            )
        );
        // Display file upload form
        $uploadFormHTML .= "<form action='{$_SERVER['REQUEST_URI']}' method='POST' name='form' enctype='multipart/form-data'>
					<div id='uploadmain'>
						<div style='margin-top:10px;padding-bottom:5px;'>
							<b>{$lang['data_import_tool_187']}</b>&nbsp;
							<select name='date_format' class='x-form-text x-form-field' style='font-family:tahoma;'>
								<option value='MDY' ".((!isset($_POST['date_format']) && DateTimeRC::get_user_format_base() != 'DMY') || (isset($_POST['date_format']) && $_POST['date_format'] == 'MDY') ? "selected" : "").">MM/DD/YYYY {$lang['global_47']} YYYY-MM-DD</option>
								<option value='DMY' ".((!isset($_POST['date_format']) && DateTimeRC::get_user_format_base() == 'DMY') || (isset($_POST['date_format']) && $_POST['date_format'] == 'DMY') ? "selected" : "").">DD/MM/YYYY {$lang['global_47']} YYYY-MM-DD</option>
							</select>
						</div>

                        <div style='padding-bottom:10px;'>
                            <b>{$lang['data_import_tool_324']}</b>
                            <select name='delimiter' class ='x-form-text x-form-field' style='front-family:tahoma;padding-right:0;padding-top:0;height:22px;margin-left:3px;'> 
                                <option value=',' selected>{$lang['global_162']}</option>
                                <option value='\t'>{$lang['global_163']}</option>
                                <option value=';'>{$lang['global_164']}</option>
                            </select>
                        </div>

						<input type='file' name='uploadedfile' size='50'
							onchange=\"document.forms['form'].elements['filepath'].value = document.forms['form'].elements['uploadedfile'].value;\">
						<input type='hidden' name='filepath' value=''>
						<div style='padding-top:10px;'>
							<button id='submit' name='submit' class='btn btn-sm btn-rcgreen fs14' onclick=\"
								if (document.forms['form'].elements['uploadedfile'].value.length < 1) {
									alert('".js_escape($lang['design_128'])."');
									return false;
								}
								var file_ext = getfileextension(trim(document.forms['form'].elements['uploadedfile'].value.toLowerCase()));
								if (file_ext != 'csv') {
									$('#filetype_mismatch_div').dialog({ bgiframe: true, modal: true, width: 530, zIndex: 3999, buttons: {
										" . RCView::tt_js2('bottom_90') . ": function() { $(this).dialog('close'); }
									}});
									return false;
								}
								document.getElementById('uploadmain').style.display='none';
								document.getElementById('progress').style.display='block';
								document.form.submit();\"><i class='fas fa-upload'></i> {$lang['design_127']}</button>
						</div>
					</div>
					<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #A5CC7A;color:#800000;'>
						<table cellpadding=10><tr>
						<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
						<td valign=top style='padding-top:20px;'>
							<b>{$lang['design_129']}</b><br>{$lang['design_130']}<br>{$lang['design_131']}</td>
						</tr></table>
					</div>
				</form>";
    }
    print RCView::div(['style' => 'max-width:800px;'],
        RCView::p([], RCView::tt('design_109')) .
            // Step 1: Download Data dictionary
            RCView::div(array('class'=>'well', 'style'=>'padding:12px;'),
                RCView::div(array('class'=>'fs14 mb-3 text-dangerrc'),
                    '<i class="fas fa-download"></i> '.RCView::tt("data_import_tool_401",'span',['class'=>'font-weight-bold'])
                ).
                RCView::div(array('class'=>'mb-3 fs13'),
                    ($status > 0 && $draft_mode > 0 ? RCView::tt("data_import_tool_405") : RCView::tt("data_import_tool_402")) .
                    " " . RCView::tt('data_import_tool_403')
                ).
                $dropdownDownloadDDBtn
            ) .
            // Step 2: Edit your data dictionary
            RCView::div(array('class'=>'well', 'style'=>'padding:12px;'),
                RCView::div(array('class'=>'fs14 mb-3 text-dangerrc'),
                    '<i class="fas fa-edit"></i> '.RCView::tt('survey_742', 'span', ['class'=>'font-weight-bold'])." ".RCView::tt("design_123",'span',['class'=>'font-weight-bold'])
                ).
                RCView::div(array('class'=>'mb-2 fs13'),
                    RCView::tt("data_import_tool_404")
                ).
                RCView::div(array('class'=>'fs12', 'style'=>'line-height:1.2;'),
                    "<b>{$lang['design_261']}</b> {$lang['design_260']}
                    <a href='" . APP_PATH_WEBROOT . "Design/data_dictionary_demo_download.php' style='text-decoration:underline;' onclick=\"
                        return alert('".remBr($lang['design_113'])."\\n\\n".remBr($lang['design_114'])."');
                    \">{$lang['design_115']}</a>, {$lang['design_116']}
                    <a onclick=\"popupvid('redcap_data_dictionary02.mp4','The Data Dictionary');\" href=\"javascript:;\"
                        style=\"text-decoration:underline;\">{$lang['design_117']}</a>{$lang['period']}
                    {$lang['design_258']} <a style='text-decoration:underline;' href='javascript:;' onclick=\"window.open('".APP_PATH_WEBROOT_PARENT."index.php?action=help#ss49','myWin','width=850, height=600, toolbar=0, menubar=0, location=0, status=0, scrollbars=1, resizable=1');\">{$lang['bottom_27']}</a>{$lang['period']}"
                )
            ) .
            // Step 3: Upload data dictionary
            RCView::div(array('class'=>'well', 'style'=>'padding:12px;'),
                RCView::div(array('class'=>'fs14 mb-3 text-dangerrc'),
                    '<i class="fas fa-upload"></i> '.RCView::tt('survey_743', 'span', ['class'=>'font-weight-bold'])." ".RCView::tt("design_126")
                ).
                $uploadFormHTML
            )
        );

	// Div for displaying popup dialog for file extension mismatch (i.e. if XLS or other)
	?>
	<div id="filetype_mismatch_div" title="<?php echo RCView::tt_js2('random_12'); ?>" style="display:none;">
		<p>
			<?php echo $lang['design_132'] ?>
			<a href="https://support.office.com/en-us/article/Import-or-export-text-txt-or-csv-files-5250ac4c-663c-47ce-937b-339e391393ba" target="_blank"
				style="text-decoration:underline;"><?php echo $lang['data_import_tool_116'] ?></a>
			<?php echo $lang['design_134'] ?>
		</p>
	</div>
	<?php

}



/**
 * Excel file was uploaded
 */
if (isset($_POST['submit']))
{
	print "<div style='clear:both;max-width:700px;'>";

    // If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
    if ($status > 0 && $draft_mode != '1') {
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
        exit;
    }

	# Save the file details that are passed to the page in the _FILES array
	foreach ($_FILES as $fn=>$f) {
		$$fn = $f;
		foreach ($f as $k=>$v) {
			$name = $fn . "_" . $k;
			$$name = $v;
		}
	}

	# If filename is blank, reload the page
	if ($uploadedfile_name == "") {
		redirect(PAGE_FULL."?pid=$project_id");
		exit;
	}

	// Get field extension
	$filetype = strtolower(substr($uploadedfile_name,strrpos($uploadedfile_name,".")+1,strlen($uploadedfile_name)));

	// If not CSV, print message, exit
	if ($filetype != "csv"){
		// If uploaded as XLSX or CSV, tell user to save as XLS and re-uploade
		$msg = ($filetype == "xls" || $filetype == "xlsx") ? RCView::tt("design_960") : RCView::tt("design_961");
		// Display error message
		print  '<div class="red" style="margin:30px 0;">
					<img src="'.APP_PATH_IMAGES.'exclamation.png"> '.$msg.'
				</div>';
		// Link to go back
		renderPrevPageBtn(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	# If Excel file, save the uploaded file (copy file from temp to folder) and prefix a timestamp to prevent file conflicts
	$uploadedfile_name = date('YmdHis') . "_pid" . PROJECT_ID . "_uploaddatadict." . $filetype;
	$uploadedfile_name = str_replace("\\", "\\\\", $upload_dir . $uploadedfile_name);

	# If moving or copying the uploaded file fails, print error message and exit
	if (!move_uploaded_file($uploadedfile_tmp_name, $uploadedfile_name))
	{
		if (!copy($uploadedfile_tmp_name, $uploadedfile_name))
		{
			print '<p><br><table width=100%><tr><td class="comp_new_error"><font color=#800000><b>' .
				 "{$lang['design_137']}</b><br>{$lang['design_138']} <span class='notranslate'>$project_contact_name " .
				 "{$lang['global_15']} <a href=\"mailto:$project_contact_email\">$project_contact_email</a></span>
				 {$lang['design_140']}</b></font></td></tr></table>";
			include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
			exit;
		}
	}
	
	// Process uploaded Excel file
	$dictionary_array = Design::excel_to_array($uploadedfile_name, $_REQUEST['delimiter']);

	// If DD returns false, then it's because the legacy column Field Units exists. If so, tell user to remove it.
	// It is no longer supported but old values defined prior to 4.0 will be preserved.
	if ($dictionary_array === false)
	{
		print 	RCView::div(array('class'=>'red','style'=>'margin:20px;'),
					RCView::img(array('src'=>'exclamation.png')) .
					RCView::b($lang['design_220']) . RCView::SP . $lang['design_219']
				);
		// Link to go back
		renderPrevPageBtn(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	// If an empty DD is uploaded, give an error
	if (empty($dictionary_array['A']))
	{
		print 	RCView::div(array('class'=>'red','style'=>'margin:20px;'),
					RCView::img(array('src'=>'exclamation.png')) .
					RCView::b($lang['global_01'].$lang['colon']) . RCView::SP . $lang['design_388']
				);
		// Link to go back
		renderPrevPageBtn(PAGE);
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	// Return warnings and errors from file (and fix any correctable errors)
	list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

	// If errors exist, display them and stop
	if (count($errors_array) > 0) {

		// Display RETURN arrow
		renderPrevPageBtn(PAGE);

		//Display errors
		Design::renderErrors($errors_array);

		//Display warnings
		Design::renderWarnings($warnings_array);

		print "<br>";
		renderPrevPageBtn(PAGE);

		// Delete uploaded file from web server since it is not usable due to errors
		unlink($uploadedfile_name);

	// If no errors exist, give user the option to commit changes
	} else {
		// Display confirmation that file was uploaded successfully without errors
		print  "<div class='darkgreen' style='margin:30px 0;'>
					<img src='".APP_PATH_IMAGES."accept.png'>
					<b>{$lang['design_141']}</b><br>
					<div style='margin-left:2em;text-indent:-1em;padding:10px 3px 3px 5px;'>";
		if (count($warnings_array) > 0) {
			// Some warnings exist
			print  "&bull; {$lang['design_142']}";
		} else {
			// No warnings exist
			print  "&bull; {$lang['design_143']}";
		}
		// Get count of fields (whether from metadata or metadata temp), excluding Form Status Fields
		$q = db_query("select count(1) from $metadata_table where project_id = $project_id and field_name != concat(form_name,'_complete')");
		$currentMetadataCount = db_result($q,0);
		print  "</div>
				<div style='margin-left:2em;text-indent:-1em;padding:10px 3px 15px 5px;'>
					&bull; {$lang['design_144']} " . count($dictionary_array['A']) . " {$lang['design_145']}
					$currentMetadataCount {$lang['design_146']} " . (($status < 1) ? $lang['design_147'] : $lang['design_148']) . "
					{$lang['design_149']}
				</div>";
		print  "</div>";

		// Display warnings
		Design::renderWarnings($warnings_array);


		// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if any forms are a survey, and if so, if has any branching logic.
		// If so, disable question auto numbering.
		if ($status < 1)
		{
			foreach (array_keys($Proj->surveys) as $this_survey_id)
			{
				$this_form = $Proj->surveys[$this_survey_id]['form_name'];
				if ($Proj->surveys[$this_survey_id]['question_auto_numbering'])
				{
					// Loop through fields in uploaded DD to see if any has branching
					$formHasBranching = false;
					foreach ($dictionary_array['B'] as $this_row=>$this_value)
					{
						if ($this_value == $this_form && $dictionary_array['L'][$this_row] != "")
						{
							$formHasBranching = true;
						}
					}
					// Survey is using auto question numbering and has branching, so set to custom numbering
					if ($formHasBranching)
					{
						// Give user a prompt as notice of this change
						?>
						<div class="yellow" style="margin:20px 0;">
							<img src="<?php echo APP_PATH_IMAGES ?>exclamation_orange.png">
							<?php echo "<b>{$lang['survey_08']} \"<span style='color:#800000;'>".strip_tags(label_decode($Proj->surveys[$this_survey_id]['title']))."</span>\"</b><br>{$lang['survey_07']} {$lang['survey_10']}" ?>
						</div>
						<?php
					}
				}
			}
		}


		// Render button to commit changes
		print  "<br><form action='{$_SERVER['REQUEST_URI']}' method='POST' name='form' enctype='multipart/form-data'>
				<div class='blue' style='padding:20px;margin:20px 0 0;'>
					<div id='uploadmain'>
						<div style='padding-bottom:5px;'>
							<b>{$lang['design_153']}</b><br>
							{$lang['design_154']}
						</div>
						<input type='hidden' name='fname' value='".htmlspecialchars(basename($uploadedfile_name), ENT_QUOTES)."'>
						<input type='hidden' name='date_format' value='".((isset($_POST['date_format']) && $_POST['date_format'] == 'DMY') ? "DMY" : "MDY")."'>
						<input type='hidden' name='delimiter' value='".(isset($_REQUEST['delimiter']) ? $_REQUEST['delimiter'] : ",")."'> 
						
						<div style='padding-top:8px;'>
							<button  name='commit' class='btn btn-xs btn-primaryrc fs14' onclick=\"
								document.getElementById('uploadmain').style.display='none';
								document.getElementById('progress').style.display='block';
								document.form.submit();
							\">{$lang['design_690']}</button>
							<a href='{$_SERVER['REQUEST_URI']}' style='margin-left:15px;'>{$lang['global_53']}</a>
						</div>
					</div>
					<div id='progress' style='display:none;background-color:#FFF;width:500px;border:1px solid #aaa;color:#800000;'>
						<table cellpadding=10><tr>
						<td valign=top><img src='" . APP_PATH_IMAGES . "progress.gif'></td>
						<td valign=top style='padding-top:20px;'>
							<b>{$lang['design_155']}</b><br>
							{$lang['design_156']}<br>
							{$lang['design_157']}
						</td>
						</tr></table>
					</div>
				</div>
				</form><br>
				</div>";
	}





// User clicked button to Commit changes
} elseif (isset($_POST['commit'])) {

    // If project is in production and another user just changed its draft_mode status, don't allow any actions here if not in draft mode
    if ($status > 0 && $draft_mode != '1') {
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
        exit;
    }

	// Process uploaded Excel file
	$dictionary_array = Design::excel_to_array(APP_PATH_TEMP . $_POST['fname'], $_REQUEST['delimiter']);

	// Return warnings and errors from file (and fix any correctable errors)
	list ($errors_array, $warnings_array, $dictionary_array) = MetaData::error_checking($dictionary_array);

	// If errors exist, display them and stop
	if (count($errors_array) > 0) {

		// Display RETURN arrow
		renderPrevPageBtn(PAGE);

		//Display errors
		Design::renderErrors($errors_array);

		//Display warnings
		Design::renderWarnings($warnings_array);

		print "<br>";
		renderPrevPageBtn(PAGE);

		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

	// Set up all actions as a transaction to ensure everything is done here
	db_query("SET AUTOCOMMIT=0");
	db_query("BEGIN");
	
	// Create a data dictionary snapshot of the *current* metadata and store the file in the edocs table
	MetaData::createDataDictionarySnapshot();

	// Save data dictionary in metadata table
	$sql_errors = MetaData::save_metadata($dictionary_array);

	// Display any failed queries to Super Users, but only give minimal info of error to regular users
	if (count($sql_errors) > 0) {

		// ERRORS OCCURRED, so undo any changes made
		db_query("ROLLBACK");
		// Set back to previous value
		db_query("SET AUTOCOMMIT=1");

		print  "<div class='red'>
					<b>{$lang['global_01']}:</b><br>
					{$lang['design_158']}";
		// Display failed queries only to super users for troubleshooting
		if (SUPER_USER)
		{
			print  "<br><br>{$lang['design_159']}<br>";

			foreach ($sql_errors as $this_query)
			{
				print "<p>".htmlspecialchars($this_query, ENT_QUOTES).";</p>";
			}
		}
		print  "</div>";

		renderPrevPageBtn(PAGE);

	}
	else
	{		
		// SURVEY QUESTION NUMBERING (DEV ONLY): Detect if any forms are a survey, and if so, if has any branching logic.
		// If so, disable question auto numbering.
		if ($status < 1)
		{
			foreach (array_keys($Proj->surveys) as $this_survey_id)
			{
				$this_form = $Proj->surveys[$this_survey_id]['form_name'];
				if ($Proj->surveys[$this_survey_id]['question_auto_numbering'] && Design::checkSurveyBranchingExists($this_form))
				{
					// Survey is using auto question numbering and has branching, so set to custom numbering
					$sql = "update redcap_surveys set question_auto_numbering = 0 where survey_id = $this_survey_id";
					db_query($sql);
				}
			}
		}

		// COMMIT CHANGES
		db_query("COMMIT");
		// Set back to previous value
		db_query("SET AUTOCOMMIT=1");

		// SUCCESS - reload page so that menu form names will update (for development projects)
		// Delete the uploaded file from the server now that its data has been imported
		@unlink($_POST['fname']);
		print  "<br><br><img src='" . APP_PATH_IMAGES . "progress_circle.gif'> &nbsp;<b>{$lang['design_160']}</b><br>";
		print  "<script type='text/javascript'>
				$(function(){
					window.location.href = app_path_webroot+page+'?pid=$project_id&upload_success&view=ddupload';
				});
				</script>";

	}

}

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
