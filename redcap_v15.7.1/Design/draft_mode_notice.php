<?php

use MultiLanguageManagement\MultiLanguage;

// When called directly, this file does nothing. Still, let's be explicit
if (!defined('PROJECT_ID')) exit('ERROR');

// Catch if user somehow gets to form editor page in production before enabling Draft Mode
if ($draft_mode == 0 && $status > 0 && isset($_GET['page']) && $_GET['page'] != '')
{
	redirect(PAGE_FULL . "?pid=$project_id");
}
//Pre-draft mode: Prompt user to enter draft mode
elseif ($draft_mode == 0 && $status > 0)
{
	if ($status == 1) {
		// If user just canceled out of Draft Mode, give confirmation that it's no longer in Draft Mode
		if (isset($_GET['msg']) && $_GET['msg'] == 'cancel_draft_mode') {
			// Display message
			displayMsg("<b>{$lang['setup_08']}</b><br>{$lang['design_264']}", "actionMsg", "left", "green", "tick.png", 7, true);
		}
		// If using DTS, then give extra warning about entering Draft Mode because of synchronicity issues
		$dtsWarn = "true"; // default true value
		if ($dts_enabled_global && $dts_enabled) {
			$dtsWarn = "confirm('" . js_escape($lang['define_events_64']) . '\n\n' . js_escape($lang['design_206'])
				. '\n\n' . js_escape($lang['design_207']) . "')";
		}
		print  "<div class='yellow' style='max-width:805px;'>
				<b>{$lang['global_02']}{$lang['colon']}</b> {$lang['design_10']}
				<div style='text-align:center;font-weight:bold;margin:10px 0 5px;'>
					{$lang['design_11']}
					<div class='mt-2'>
						<button class='btn btn-xs btn-defaultrc fs13' onclick=\"
							if ($dtsWarn) window.location.href='".APP_PATH_WEBROOT."Design/draft_mode_enter.php?pid='+pid;
						\">{$lang['design_376']}</button>
					</div>
				</div>
			</div>";
	} else {
		// Inactive
		print  "<div class='yellow mt-3' style='max-width:805px;'>
				<b>{$lang['global_02']}{$lang['colon']}</b> {$lang['design_791']}
				</div>";
		include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
		exit;
	}

// Inactive (but draft mode is enabled)
} elseif ($draft_mode > 0 && $status > 1)
{
    // Inactive
    print  "<div class='yellow mt-3' style='max-width:805px;'>
				<b>{$lang['global_02']}{$lang['colon']}</b> {$lang['design_791']}
				</div>";
    include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
    exit;

//Draft mode (show changes)
} elseif ($draft_mode == 1 && $status > 0 && $_SERVER['REQUEST_METHOD'] != 'POST')
{
	// If user just canceled out of Draft Mode, give confirmation that it's no longer in Draft Mode
	if (isset($_GET['msg']) && $_GET['msg'] == 'enabled_draft_mode') {
		// Display message
		displayMsg("<b>{$lang['setup_08']}</b><br>{$lang['design_385']}", "actionMsg", "left", "green", "tick.png", 13, (UIState::getUIStateValue('', 'online-designer', 'draft-preview-announcement') == '1'));
	}

	$eConsentVersionUpdateMsg = "";
    /*
	// Get any modified forms with e-Consent Framework enabled AND is also using e-Consent version number
	$econsentFormsWithModifiedFields = Design::getEconsentFormsWithModifiedFields(true);
	if (!empty($econsentFormsWithModifiedFields)) {
		$eConsentVersionUpdateMsg .= RCView::b('<i class="fas fa-exclamation-circle"></i> '.$lang['design_732']) . " " . $lang['design_733'] .
			RCView::div(array('style'=>'margin-top: 5px;'), 
				RCView::u($lang['design_734'])
			);
		foreach ($econsentFormsWithModifiedFields as $this_form) {
			$eConsentVersionUpdateMsg .= RCView::div(array('style'=>'color:#C00000;font-weight:700;'), 
				'"' . RCView::escape($Proj->forms[$this_form]['menu']) . '", ' . 
				$lang['survey_1162'] . " " . Econsent::getEconsentSurveySettings($Proj->forms[$this_form]['survey_id'])['version']
			);
		}
		$eConsentVersionUpdateMsg = RCView::div(array('class'=>'yellow', 'style'=>'margin-top:10px;font-size:12px;'), $eConsentVersionUpdateMsg);
	}
    */

	$draft_preview_checked = Design::isDraftPreview() ? "checked='checked'" : "";
	$draft_preview_supported = Design::canUseDraftPreview() ? "true" : "false";
	print  "<div class='yellow' style='max-width:805px;'>
				<b>{$lang['design_14']}</b>
				<a href='javascript:;' style='margin-left:10px;color:#800000;font-size:11px;' onclick=\"
					$('#draftChangeInstr').toggle('blind','fast');
				\">{$lang['global_58']}</a>
				<div id='draftChangeInstr' style='display:none;'>{$lang['design_177']} {$lang['design_175']}</div>
				<table cellpadding='0' cellspacing='0'>
					<tr>
						<td valign='top' style='padding:10px 40px 0px 10px;'>
							<input type='button' class='jqbutton' value='".htmlspecialchars($lang['design_255'], ENT_QUOTES)."' onclick=\"
								if (status > 1) {
									simpleDialog('".js_escape($lang['design_481'])."','".js_escape($lang['global_03'])."');
								} else {
									$('#confirm-review').dialog({ bgiframe: true, modal: true, width: 600, buttons: {
										'".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
										'".js_escape($lang['survey_200'])."': function() {
											$('#confirm-review').parent().find('.ui-dialog-buttonpane button:eq(1)').html('".js_escape($lang['design_160'])."');
											$('#confirm-review').parent().find('.ui-dialog-buttonpane button:eq(0)').css('display','none');
											showProgress(1);
											window.location.href=app_path_webroot+'Design/draft_mode_review.php?pid='+pid;
										}
									} });
								}
							\">
							<div id='draft-preview-container'>
								<div class='form-check form-switch'>
									<input class='form-check-input' type='checkbox' role='switch' id='draft-preview-switch' onclick=\"setDraftPreview(this, event, $draft_preview_supported);\" $draft_preview_checked>
									<label class='form-check-label' for='draft-preview-switch'>".RCView::tt("draft_preview_01")."</label>
									<a href='javascript:;' class='help' title='".js_escape($lang['global_58'])."' onclick=\"showDraftPreviewInfo();\">?</a>
								</div>
							</div>
						</td>
						<td valign='top' style='padding-bottom:5px;'>
							" . Design::renderCountFieldsAddDel() . "
							<a href='".APP_PATH_WEBROOT."Design/project_modifications.php?pid=$project_id&ref=".PAGE."' style='font-size:12px;'><i class=\"fa-solid fa-magnifying-glass mr-1\"></i>{$lang['design_436']}</a>							
                            ".RCView::span(array('style'=>'font-size:12px;color:#777;margin:0 10px;'), "&ndash; ".$lang['global_47']." &ndash;")."
                            <a href='javascript:;' style='color:#800000;font-size:12px;' onclick=\"
                                $('#draft-cancel').dialog({ bgiframe: true, modal: true, width: 600, buttons: {
                                    '".js_escape($lang['global_53'])."': function() { $(this).dialog('close'); },
                                    '".js_escape($lang['design_256'])."': function() {
                                        window.location.href=app_path_webroot+'Design/draft_mode_cancel.php?pid='+pid;
                                    }
                                } });
                            \"><i class=\"fa-solid fa-xmark mr-1\"></i>{$lang['design_256']}</a>
						</td>
					</tr>
				</table>
			</div>
			<br>

			<!-- Hidden Dialogs -->
			<div id='draft-cancel' title=\"".js_escape2($lang['design_265'])."\" style='display:none;'>
				<p>{$lang['design_257']}</p>
				".(MultiLanguage::isActive($project_id) && MultiLanguage::hasLanguages($project_id) ? RCView::div(['class'=>'yellow mt-3'], '<i class="fas fa-globe"></i> ' . RCView::tt('multilang_224')) : "")."
			</div>
			<div id='confirm-review' title=\"".js_escape2($lang['design_16'])."\" style='display:none;'>
				<p>
					{$lang['design_17']}
					".($auto_prod_changes > 0 ? "<div style='background:#f5f5f5;border:1px solid #ddd;padding:4px;'>{$lang['design_287']}</div>" : "<br>")."
					".(MultiLanguage::isActive($project_id) && MultiLanguage::hasLanguages($project_id) ? RCView::div(['class'=>'yellow mt-3'], '<i class="fas fa-globe"></i> ' . RCView::tt('multilang_223')) : "")."
					<br>
					<img src='" . APP_PATH_IMAGES . "star.png'> {$lang['edit_project_55']}
					<a style='text-decoration:underline;' href='".APP_PATH_WEBROOT."index.php?pid=$project_id&route=IdentifierCheckController:index'>{$lang['identifier_check_01']}</a> {$lang['edit_project_56']}
					$eConsentVersionUpdateMsg
				</p>
			</div>";

//Post-draft mode: Waiting approval from administrator
} elseif ($draft_mode == 2 && $status > 0)
{
	## Give special notification to Super User for reviewing changes
	if (UserRights::isSuperUserNotImpersonator()) {
		print  "<div class='red' style='margin:20px 0;max-width:805px;'>
					<i class=\"fas fa-exclamation-circle\"></i> <b>{$lang['design_19']}</b>
					{$lang['design_20']} {$lang['design_21']}{$lang['period']}
					<div class='mt-2'>
						<button class='btn btn-xs btn-defaultrc fs13' onclick=\"window.location.href='" . APP_PATH_WEBROOT . "Design/project_modifications.php?pid=$project_id';\">{$lang['design_21']}</button>
					</div>
				</div>";
	}

	## Give note to normal user
	// If using auto prod changes, then give user explanation of why their changes weren't approved automatically
	$explainText = "";
	if ($auto_prod_changes > 0) {
		if ($auto_prod_changes == '1') {
			$explainText = $lang['design_284'];
		} elseif ($auto_prod_changes == '2') {
			$explainText = $lang['design_285'];
		} elseif ($auto_prod_changes == '3') {
			$explainText = $lang['design_290'];
		} elseif ($auto_prod_changes == '4') {
			$explainText = $lang['design_291'];
		}
		$explainText .= " " . $lang['design_286'];
		$explainText = "<div class='my-2'>
							<a href='javascript:;' onclick=\"$('#explainNoAutoChanges').toggle('fade');\" style='color:#800000;'>{$lang['design_283']}</a>
							<div style='display:none;margin-top:10px;border:1px solid #ccc;padding:8px;' id='explainNoAutoChanges'>$explainText</div>
						</div>";
	}
	// Display message
	print  "<div class='yellow' style='padding:10px;max-width:805px;'>
				<i class=\"far fa-clock\"></i> <b>{$lang['design_22']}</b>
				<div class='my-2'>
					{$lang['design_23']} {$lang['design_24']} <b>$project_contact_name</b>
					{$lang['leftparen']}<a class='notranslate' href='mailto:$project_contact_email' style='text-decoration:underline;'>$project_contact_email</a>{$lang['rightparen']}{$lang['period']}
				</div>
				$explainText
				<div class='mt-2'>
					<img src='".APP_PATH_IMAGES."zoom.png'>
					{$lang['design_437']}
					<a href='".APP_PATH_WEBROOT."Design/project_modifications.php?pid=$project_id&ref=".PAGE."'
						style=''>{$lang['design_18']}</a>{$lang['period']}
				</div>
			</div>";

}
