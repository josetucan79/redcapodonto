<?php

use ExternalModules\ExternalModules;
use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\ProjectActivityInvalidation;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';
// Determine whether to output a stats syntax file
$stats_packages = array('r', 'spss', 'stata', 'sas');
$allowable_data_formats = array('csvraw', 'csvlabels', 'odm', 'odm_project');
$outputFormat = (!in_array($_POST['export_format'], $stats_packages) && !in_array($_POST['export_format'], $allowable_data_formats)) ? 'csvraw' : $_POST['export_format'];
// Export DAG names?
$outputDags = ($user_rights['group_id'] == '' && isset($_POST['export_groups']) && $_POST['export_groups'] == 'on');
// Export survey fields
$outputSurveyFields = (isset($_POST['export_survey_fields']) && $_POST['export_survey_fields'] == 'on');
$outputMDCodes = (isset($_POST['output_md_codes']) && $_POST['output_md_codes'] == 'on');
// ODM Only: Include ODM metadata
$replaceFileUploadDocId = ($outputFormat != 'odm');
$includeOdmMetadata = false;
if ($outputFormat == 'odm_project') {
	$includeOdmMetadata = true;
	$outputFormat = 'odm';
	$replaceFileUploadDocId = !(isset($_POST['odm_include_files']) && $_POST['odm_include_files'] == 'on');
}
// De-Identification settings
$hashRecordID = ((isset($user_rights['forms_export'][$Proj->firstForm]) && $user_rights['forms_export'][$Proj->firstForm] > 1 && $Proj->table_pk_phi)
				|| (isset($_POST['deid-hashid']) && $_POST['deid-hashid'] == 'on'));
$removeIdentifierFields = (isset($_POST['deid-remove-identifiers']) && $_POST['deid-remove-identifiers'] == 'on');
$removeUnvalidatedTextFields = (isset($_POST['deid-remove-text']) && $_POST['deid-remove-text'] == 'on');
$removeNotesFields = (isset($_POST['deid-remove-notes']) && $_POST['deid-remove-notes'] == 'on');
$removeDateFields = (isset($_POST['deid-dates-remove']) && $_POST['deid-dates-remove'] == 'on');
$dateShiftDates = (!$removeDateFields && isset($_POST['deid-dates-shift']) && $_POST['deid-dates-shift'] == 'on');
$dateShiftSurveyTimestamps = (!$removeDateFields && isset($_POST['deid-surveytimestamps-shift']) && $_POST['deid-surveytimestamps-shift'] == 'on');
// For de-id rights, make sure survey timestamps are date shifted if dates are date shifted
if ($dateShiftDates && !$dateShiftSurveyTimestamps && !UserRights::isSuperUserNotImpersonator()) {
    // Detect if user has de-id rights for all forms. If so,
    $deidAllForms = true;
    foreach ($user_rights['forms_export'] as $thisFormExportRight) {
        if ($thisFormExportRight != '2') {
            $deidAllForms = false;
            break;
        }
    }
    if ($deidAllForms) $dateShiftSurveyTimestamps = true;
}
// Instrument and event filtering
$selectedInstruments = (isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : ($_POST['report_id'] == 'SELECTED' ? array_keys($Proj->forms) : array()));
$selectedEvents = (isset($_GET['events']) ? explode(',', $_GET['events']) : ($_POST['report_id'] == 'SELECTED' ? array_keys($Proj->eventInfo) : array()));
// Obtain any dynamic filters selected from query string params
$liveFilterLogic = $liveFilterGroupId = $liveFilterEventId = "";
if (isset($_POST['live_filters_apply']) && $_POST['live_filters_apply'] == 'on') {
	list ($liveFilterLogic, $liveFilterGroupId, $liveFilterEventId) = DataExport::buildReportDynamicFilterLogic($_POST['report_id']);
}
// Save defaults for CSV delimiter and decimal character
$csvDelimiter = (isset($_POST['csvDelimiter']) && DataExport::isValidCsvDelimiter($_POST['csvDelimiter'])) ? $_POST['csvDelimiter'] : ",";
UIState::saveUIStateValue('', 'export_dialog', 'csvDelimiter', $csvDelimiter);
if ($csvDelimiter == 'tab' || $csvDelimiter == 'TAB') $csvDelimiter = "\t";
$decimalCharacter = isset($_POST['decimalCharacter']) ? $_POST['decimalCharacter'] : '';
UIState::saveUIStateValue('', 'export_dialog', 'decimalCharacter', $decimalCharacter);
// Export blank values for gray instrument status?
$returnBlankForGrayFormStatus = (isset($_POST['returnBlankForGrayFormStatus']) && $_POST['returnBlankForGrayFormStatus'] == '1');

## Rapid Retrieval: Cache salt
// Generate a form-level access salt for caching purposes: Create array of all forms represented by the report's fields
$reportAttr = DataExport::getReports($_POST['report_id']);
$reportFields = $reportAttr['fields'] ?? [];
$reportForms = [];
foreach ($reportFields as $thisField) {
    $thisForm = $Proj->metadata[$thisField]['form_name'];
    if (isset($reportForms[$thisForm])) continue;
    $reportForms[$thisForm] = true;
}
$reportFormsAccess = array_intersect_key($user_rights['forms_export']??[], $reportForms);
// Use some user privileges as additional salt for the cache
$cacheManager = CacheFactory::manager(PROJECT_ID);
$cacheOptions = [REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature($project_id)]];
$cacheOptions[REDCapCache::OPTION_SALT] = [];
$cacheOptions[REDCapCache::OPTION_SALT][] = ['dag'=>$user_rights['group_id']];
$reportFormsAccessSalt = [];
foreach ($reportFormsAccess as $thisForm=>$thisAccess) {
    $reportFormsAccessSalt[] = "$thisForm:$thisAccess";
}
$cacheOptions[REDCapCache::OPTION_SALT][] = ['form-export-rights'=>implode(",", $reportFormsAccessSalt)];
// If the report has filter logic containing datediff() with today or now, then add more salt since these will cause different results with no data actually changing.
if (strpos($reportAttr['limiter_logic'], 'datediff') !== false) {
    list ($ddWithToday, $ddWithNow) = containsDatediffWithTodayOrNow($reportAttr['limiter_logic']);
    if ($ddWithNow) $cacheManager->setState(new DisabledState());  // disable the cache since will never be used
    elseif ($ddWithToday) $cacheOptions[REDCapCache::OPTION_SALT][] = ['datediff'=>TODAY];
}
// If the report has filter logic containing a [user-X] smart variable, then add the USERID to the salt
if (strpos($reportAttr['limiter_logic'], '[user-') !== false) {
    $cacheOptions[REDCapCache::OPTION_SALT][] = ['user'=>USERID];
}

// Export the data for this report
$saveSuccess = 	DataExport::doReport($_POST['report_id'], 'export', $outputFormat, false, false, $outputDags, $outputSurveyFields,
    $removeIdentifierFields, $hashRecordID, $removeUnvalidatedTextFields, $removeNotesFields,
    $removeDateFields, $dateShiftDates, $dateShiftSurveyTimestamps,
    $selectedInstruments, $selectedEvents, false, false, $includeOdmMetadata, true, $replaceFileUploadDocId,
    $liveFilterLogic, $liveFilterGroupId, $liveFilterEventId, false, $csvDelimiter, $decimalCharacter,
    array(), false, true, false, false, $returnBlankForGrayFormStatus, true, true, $project_id,
    false, false, $cacheManager, $cacheOptions);
if ($saveSuccess === false) exit('0');
// Parse response to get the doc_id's of the files
$data_edoc_id   = $saveSuccess[0];
$syntax_edoc_id = $saveSuccess[1];
// Are we exporting missing data codes in the report?
$exportContainsMissingDataCodes = false;
if (!empty($missingDataCodes)) {
	if (is_numeric($_POST['report_id'])) {
		$thisReport = DataExport::getReports($_POST['report_id']);
		$exportContainsMissingDataCodes = ($thisReport['output_missing_data_codes'] == '1');
	} else {
		$exportContainsMissingDataCodes = true;
	}
}
$exportContainsMissingDataCodesNote = '';
if ($exportContainsMissingDataCodes) {
	$exportContainsMissingDataCodesNote = '<div class="mt-3" style="color:#A00000;">'.$lang['data_export_tool_242'].'</div>';
}

// Add note that the cache was used during the export
$cacheNote = "";


// Set language based on export file type
switch ($outputFormat)
{
	case "odm":
		if ($includeOdmMetadata) {
			// REDCap project export
			$docs_header = $lang['data_export_tool_200'];
			$docs_logo = "odm_redcap.gif";
			$instr = $lang['data_export_tool_203'];
		} else {
			// ODM data
			$docs_header = $lang['data_export_tool_197'];
			$docs_logo = "odm.png";
			$instr = $lang['data_export_tool_198'];
		}
		break;
	case "spss":
		$docs_header = $lang['data_export_tool_07'];
		$docs_logo = "spsslogo_small.png";
		$instr = $lang['data_export_tool_08'].'<br>
				<a href="javascript:;" style="text-decoration:underline;font-size:11px;" onclick=\'$("#spss_detail").toggle("fade");\'>'.$lang['data_export_tool_08b'].'</a>
				<div style="display:none;border-top:1px solid #aaa;margin-top:5px;padding-top:3px;" id="spss_detail"><u>'.
					$lang['data_export_tool_247'].'</u><br>'.
					$lang['data_export_tool_08c'].' /Users/YourName/Documents/<br><br>'.
					$lang['data_export_tool_08d'].'
					<br><font color=green>FILE HANDLE data1 NAME=\'DATA.CSV\' LRECL=10000.</font><br><br>'.
					$lang['data_export_tool_08e'].'<br>
					<font color=green>FILE HANDLE data1 NAME=\'<font color=red>/Users/YourName/Documents/</font>DATA.CSV\' LRECL=10000.</font><br><br>'.
					$lang['data_export_tool_08f'].'
				</div>';
		$instr .= $exportContainsMissingDataCodesNote;
		break;
	case "sas":
		$docs_header = $lang['data_export_tool_11'];
		$docs_logo = "saslogo_small.png";
		$instr = $lang['data_export_tool_244'].'
				<div class="mt-1"><font color=green>%let csv_file = \'MyProject_DATA_NOHDRS.csv\'; </font></div>
				<div class="mt-1 mb-1">'.$lang['data_export_tool_245'].'</div>
				<div><font color=green>%let csv_file = \'<font color=red>/Users/JoeUser/Documents/</font>MyProject_DATA_NOHDRS.csv\'; </font></div>
				<div class="mt-1 mb-1">'.$lang['global_46'].'</div>
				<div><font color=green>%let csv_file = \'<font color=red>C:\Users\JoeUser\Desktop\</font>MyProject_DATA_NOHDRS.csv\'; </font></div>
			</div>';
		break;
	case "stata":
		$docs_header = $lang['data_export_tool_187'];
		$docs_logo = "statalogo_small.png";
		$instr = $lang['data_export_tool_14'];
		$instr .= $exportContainsMissingDataCodesNote;
		break;
	case "r":
		$docs_header = $lang['data_export_tool_09'];
		$docs_logo = "rlogo_small.png";
		$instr = $lang['data_export_tool_10'];
		$instr .= $exportContainsMissingDataCodesNote;
		break;
	default:
		$docs_header = $lang['data_export_tool_172'] . " "
					 . ($outputFormat == 'csvraw' ? $lang['report_builder_49'] : $lang['report_builder_50']);
		$docs_logo = "excelicon.gif";
		$instr = "{$lang['data_export_tool_118']}<br><br><i>{$lang['global_02']}{$lang['colon']} {$lang['data_export_tool_17']}</i>";
}

// SEND-IT LINKS: If Send-It is not enabled for Data Export and File Repository, then hide the link to utilize Send-It
$senditLinks = "";
if ($sendit_enabled == '1' || $sendit_enabled == '3')
{
	$senditLinks = 	RCView::div(array('style'=>''),
						RCView::img(array('src'=>'mail_small.png', 'style'=>'vertical-align:middle;')) .
						RCView::a(array('href'=>'javascript:;', 'style'=>'vertical-align:middle;line-height:10px;color:#666;font-size:10px;text-decoration:underline;',
							'onclick'=>"displaySendItExportFile($data_edoc_id);"), $lang['docs_53']
						)
					) .
					RCView::div(array('id'=>"sendit_$data_edoc_id", 'style'=>'display:none;padding:4px 0 4px 6px;'),
						// Syntax file
						($syntax_edoc_id == null ? '' :
							RCView::div(array(),
								" &bull; " .
								RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:10px;', 'onclick'=>"popupSendIt($syntax_edoc_id,2);"),
									$lang['docs_55']
								)
							)
						) .
						// Data file
						RCView::div(array(),
							" &bull; " .
							RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:10px;', 'onclick'=>"popupSendIt($data_edoc_id,2);"),
								(($syntax_edoc_id != null || $outputFormat == 'odm') ? $lang['docs_54'] : ($outputFormat == 'csvraw' ? $lang['data_export_tool_119'] : $lang['data_export_tool_120']))
							)
						)
					);
}

// Display Pathway Mapper icon for SPSS or SAS only
$pathway_mapper = "";
if ($outputFormat == "spss") {
	$pathway_mapper =  "<div style='padding-bottom:5px;'>
							<a href='".APP_PATH_WEBROOT."DataExport/spss_pathway_mapper.php?pid=$project_id'
							><img src='".APP_PATH_IMAGES."download_pathway_mapper.gif'></a> &nbsp;
						</div>";
}


## NOTICES FOR CITATIONS (GRANT AND/OR SHARED LIBRARY) AND DATE-SHIFT NOTICE
$citationText = "";
// Do not display grant statement unless $grant_cite has been set for this project.
if ($grant_cite != "") {
	$citationText .= RCView::li(['class'=>'mb-2'],
						"{$lang['data_export_tool_297']} <b>$grant_cite</b>{$lang['period']}"
					 );
}
// REDCap 2009 publication citation
$citationText .= RCView::li(['class'=>'mb-2'],
					$lang['data_export_tool_298'] . " <a href='https://redcap.vumc.org/consortium/cite.php' target='_blank' style='text-decoration:underline;'>{$lang['data_export_tool_301']}</a>"
				 );
// Shared Library citation: If instruments have been downloaded from the Shared Library
if ($Proj->formsFromLibrary()) {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			Jihad S. Obeid, Catherine A. McGraw, Brenda L. Minor, José G. Conde, Robert Pawluk, Michael Lin, Janey Wang, Sean R. Banks, Sheree A. Hemphill, Rob Taylor, Paul A. Harris,
			&quot;<b>Procurement of shared data instruments for Research Electronic Data Capture (REDCap)</b>&quot;, 
			Journal of Biomedical Informatics,
			Volume 46, Issue 2,
			2013,
			Pages 259-265,
			ISSN 1532-0464.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://doi.org/10.1016/j.jbi.2012.10.006'>https://doi.org/10.1016/j.jbi.2012.10.006</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
						"{$lang['data_export_tool_300']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
					 );
}
// CDIS citation
if ($GLOBALS['realtime_webservice_type'] == 'FHIR' || $GLOBALS['datamart_enabled'] == '1') {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			A.C. Cheng, S.N. Duda, R. Taylor, F. Delacqua, A.A. Lewis, T. Bosler, K.B. Johnson, P.A. Harris,
			&quot;<b>REDCap on FHIR: Clinical Data Interoperability Services</b>&quot;, 
			Journal of Biomedical Informatics,
			Volume 121,
			2021,
			103871,
			ISSN 1532-0464.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://doi.org/10.1016/j.jbi.2021.103871'>https://doi.org/10.1016/j.jbi.2021.103871</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
		"{$lang['data_export_tool_304']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
	);
}
// REDCap Mobile App citation
$sql = "select 1 from redcap_mobile_app_log where event = 'INIT_PROJECT' and project_id = $project_id limit 1";
if (db_num_rows(db_query($sql))) {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			Paul A Harris, Giovanni Delacqua, Robert Taylor, Scott Pearson, Michelle Fernandez, Stephany N Duda,
			&quot;<b>The REDCap Mobile Application: a data collection platform for research in regions or situations with internet scarcity</b>&quot;, 
			JAMIA Open, Volume 4, Issue 3, July 2021, ooab078.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://doi.org/10.1093/jamiaopen/ooab078'>https://doi.org/10.1093/jamiaopen/ooab078</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
		"{$lang['data_export_tool_305']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
	);
}
// MyCap citation
$sql = "SELECT 1 FROM redcap_external_modules e, redcap_external_module_settings s
		WHERE e.directory_prefix = 'mycap' AND e.external_module_id = s.external_module_id and s.`key` = 'enabled' 
		and s.`value` = 'true' and s.project_id = $project_id limit 1";
$mycapModuleEnabled = (db_num_rows(db_query($sql)));
if ($mycapModuleEnabled || (isset($GLOBALS['mycap_enabled']) && $GLOBALS['mycap_enabled'] == '1')) {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			Paul A Harris, Jonathan Swafford, Emily S Serdoz, Jessica Eidenmuller, Giovanni Delacqua, Vaishali Jagtap, Robert J Taylor, Alexander Gelbard, Alex C Cheng, Stephany N Duda,
			&quot;<b>MyCap: a flexible and configurable platform for mobilizing the participant voice</b>&quot;, 
			JAMIA Open, Volume 5, Issue 2, July 2022, ooac047.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://doi.org/10.1093/jamiaopen/ooac047'>https://doi.org/10.1093/jamiaopen/ooac047</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
		"{$lang['data_export_tool_306']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
	);
}
// E-Consent citation
if ($Proj->hasEconsentSurveys) {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			Lawrence CE, Dunkel L, McEver M, Israel T, Taylor R, Chiriboga G, Goins KV, Rahn EJ, Mudano AS, Roberson ED, Chambless C, Wadley VG, Danila MI, Fischer MA, Joosten Y, Saag KG, Allison JJ, Lemon SC, Harris PA,
			&quot;<b>A REDCap-based model for electronic consent (eConsent): Moving toward a more personalized consent</b>&quot;, 
			J Clin Transl Sci. 2020 Apr 3;4(4):345-353.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://doi.org/10.1017/cts.2020.30'>https://doi.org/10.1017/cts.2020.30</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
		"{$lang['data_export_tool_307']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
	);
}
// EM Framework citation if any modules are enabled
$enabledModules = ExternalModules::getEnabledModules(PROJECT_ID);
if (!empty($enabledModules)) {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			Cheng, Alex C., Stephany N. Duda, Kyle McGuffin, Mark McEver, Rob Taylor, Günther A. Rezniczek, Andrew Martin, Eduardo Morales, and Paul A. Harris. 
			&quot;<b>Supporting rapid innovation in research data capture and management: the REDCap external module framework.</b>&quot; 
			Journal of the American Medical Informatics Association 32, no. 7 (2025): 1149-1156.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://doi.org/10.1093/jamia/ocaf073'>https://doi.org/10.1093/jamia/ocaf073</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
		"{$lang['data_export_tool_314']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
	);
}
// Randomization citation
if ($randomization && Randomization::setupStatus(PROJECT_ID)) {
	$dlg1 = "<code style='font-size:15px;color:#333;'>
			Stevens, Luke, Nan Kennedy, Rob J. Taylor, Adam Lewis, Frank E. Harrell, Matthew S. Shotwell, Emily S. Serdoz et al. 
			&quot;<b>A REDCap Advanced Randomization Module to Meet the Needs of Modern Trials.</b>&quot; Available at SSRN 5261054.
			<a target='_blank' style='text-decoration:underline;font-size:15px;' href='https://dx.doi.org/10.2139/ssrn.5261054'>https://dx.doi.org/10.2139/ssrn.5261054</a></code>";
	$citationText .= RCView::li(['class'=>'mb-2'],
		"{$lang['data_export_tool_315']} <a href='javascript:;' style='text-decoration:underline;' onclick=\"simpleDialog('".js_escape($dlg1)."','".RCView::tt_js('data_export_tool_302')."',null,650);\">{$lang['data_export_tool_303']}</a>"
	);
}
// Wrap all citations in an ordered list
$citationText = RCView::fieldset(array('style'=>'margin-top:10px;padding-left:8px;background-color:#FFFFD3;border:1px solid #FFC869;color:#B00000;'),
	RCView::legend(array('class'=>'font-weight-bold fs14 mt-2'),
		'<i class="fa-solid fa-book"></i> '.$lang['data_export_tool_295'] . ($grant_cite != "" ? " ".$lang['data_export_tool_296'] : "")
	) .
	RCView::div(array('class'=>'p-1 mt-1'),
		$lang['data_export_tool_299']
	) .
	RCView::ol(array('class'=>'ms-3 ps-1 pe-3 pt-2 pb-0'),
		$citationText
	)
);


// If dates were date-shifted, give note of that.
$dateShiftText = "";
if ($dateShiftDates) {
	$dateShiftText = RCView::fieldset(array('class'=>'red', 'style'=>'margin-top:10px;padding:0 0 0 8px;max-width:1000px;'),
						RCView::legend(array('style'=>'font-weight:bold;'),
							$lang['global_03']
						) .
						RCView::div(array('style'=>'padding:5px 8px 8px 2px;'),
							"{$lang['data_export_tool_85']} $date_shift_max {$lang['data_export_tool_86']}"
						)
					);
}

// RESPONSE
$dialog_title = 	RCView::img(array('src'=>'tick.png', 'style'=>'vertical-align:middle')) .
					RCView::span(array('style'=>'color:green;vertical-align:middle;font-size:15px;'), $lang['data_export_tool_05']);
$dialog_content = 	RCView::div(array('style'=>'margin-bottom:20px;'),
						$lang['data_export_tool_183'] .
						$citationText .
						$dateShiftText
					) .
					RCView::div(array('style'=>'background-color:#F0F0F0;border:1px solid #888;padding:10px 5px;margin-bottom:10px;'),
						RCView::table(array('style'=>'border-collapse:collapse;width:100%;table-layout:fixed;'),
							RCView::tr(array(),
								RCView::td(array('rowspan'=>'3', 'valign'=>'top', 'style'=>'padding-left:10px;width:70px;'),
									RCView::img(array('src'=>$docs_logo, 'title'=>$docs_header))
								) .
								RCView::td(array('rowspan'=>'3', 'valign'=>'top', 'style'=>'line-height:14px;border-right:1px solid #ccc;font-family:Verdana;font-size:11px;padding-right:20px;'),
									RCView::div(array('style'=>'font-size:14px;font-weight:bold;margin-bottom:10px;'), $docs_header) .
									$instr
								) .
								RCView::td(array('valign'=>'top', 'class'=>'nowrap', 'style'=>'color:#666;font-size:11px;padding:0 5px 0 10px;width:145px;'),
									$lang['data_export_tool_184']
								)
							) .
							// Download icons
							RCView::tr(array(),
								RCView::td(array('valign'=>'top', 'class'=>'nowrap', 'style'=>'padding:10px 0 0 20px;'),
									// Syntax file download icon
									($syntax_edoc_id == null ? '' :
										RCView::a(array('href'=>APP_PATH_WEBROOT."index.php?pid=$project_id&route=FileRepositoryController:download&id=$syntax_edoc_id"),
											trim(DataExport::getDownloadIcon($outputFormat))
										)
									) .
									RCView::SP . RCView::SP . RCView::SP .
									// Data CSV file download icon
									RCView::a(array('href'=>APP_PATH_WEBROOT."index.php?pid=$project_id&route=FileRepositoryController:download&id=$data_edoc_id" .
										// For R and Stata, add "exporttype" flag to remove BOM from UTF-8 encoded files because the BOM can cause data import issues into R and Stata
										($outputFormat == 'r' ? '&exporttype=R' : ($outputFormat == 'stata' ? '&exporttype=STATA' : ''))),
										trim(DataExport::getDownloadIcon(($syntax_edoc_id == null ? $outputFormat : ''), $dateShiftDates, $includeOdmMetadata))
									) .
									// Pathway mapper file (for SAS and SPSS only)
									$pathway_mapper
								)
							) .
							// Send-It links
							RCView::tr(array(),
								RCView::td(array('valign'=>'bottom', 'style'=>'padding-left:20px;'), $senditLinks)
							)
						)
					) .
                    $cacheNote;

header('Content-Type: application/json');
print json_encode_rc(array('title'=>$dialog_title, 'content'=>$dialog_content));