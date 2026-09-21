<?php

use Vanderbilt\REDCap\Classes\Cache\REDCapCache;
use Vanderbilt\REDCap\Classes\Cache\CacheFactory;
use Vanderbilt\REDCap\Classes\Cache\CacheLogger;
use Vanderbilt\REDCap\Classes\Cache\States\DisabledState;
use Vanderbilt\REDCap\Classes\Cache\InvalidationStrategies\ProjectActivityInvalidation;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

## PERFORMANCE: Kill any currently running processes by the current user/session on THIS page
System::killConcurrentRequests(10);

// Get report info
$report_id = $_POST['report_id'];
if ($report_id != 'ALL' && $report_id != 'SELECTED') {
	$report_id = (int)$report_id;
}
$report = DataExport::getReports($report_id);
// Display an error if this report does not belong to the current project
if (isinteger($report_id) && $report['project_id'] != PROJECT_ID) {
    print RCView::div(array('class' => 'red'), $lang['global_01'].$lang['colon']." ".$lang['data_export_tool_180']);
    exit;
}

// Checks for public reports
if (DataExport::isPublicReport())
{
	DataExport::checkReportHash($report_id);
	$report = DataExport::getReports($report_id);
	// Make sure user has access to this report if viewing inside a project
	$noAccess = false;
	if ($report['is_public'] != '1') {
		// If viewing a public report link that is no longer set as "public", return error message
		$type = 'error';
		$noAccess = true;
		$errorMsg = $lang['report_builder_184'];
	} else {
		$reports = DataExport::getReports($report_id);
		// List of fields to verify if field is phi
		$fields = $reports['fields'];
		foreach ($fields as $field_name) {
			if ($Proj->metadata[$field_name]['field_phi'] == '1') {
				$type = 'notice';
				$noAccess = true;
				$errorMsg = $lang['report_builder_188'];
				break;
			}
		}
		// If using Custom Record Label/Secondary Unique Field, return error if any of those fields are identifiers
		if ($noAccess == false && in_array($Proj->table_pk, $fields) && trim($secondary_pk.$custom_record_label) != '') {
			if ($secondary_pk != '' && $Proj->metadata[$secondary_pk]['field_phi'] == '1' && $Proj->project['secondary_pk_display_value']) {
				// Secondary Unique Field is an Identifier
				$type = 'notice';
				$noAccess = true;
				$errorMsg = $lang['report_builder_217'];
			} elseif (trim($custom_record_label) != '') {
				// Get the variables in $custom_record_label and then check if any are Identifiers
				$custom_record_label_fields = array_unique(array_keys(getBracketedFields($custom_record_label, true, true, true)));
				foreach ($custom_record_label_fields as $field_name) {
					if ($Proj->metadata[$field_name]['field_phi'] == '1') {
						$type = 'notice';
						$noAccess = true;
						$errorMsg = $lang['report_builder_218'];
						break;
					}
				}
			}
		}
	}
	// Display the error if necessary
	if ($noAccess) {
		print RCView::div(array('class' => 'red my-5'), RCView::b(($type == 'error') ? $lang['global_01'] : $lang['global_03'] . $lang['colon']) . " " . $errorMsg);
		exit;
	}
}

$script_time_start = microtime(true);

## Rapid Retrieval: Cache salt
// Generate a form-level access salt for caching purposes: Create array of all forms represented by the report's fields
$reportAttr = DataExport::getReports($report_id);
$reportFields = $reportAttr['fields'] ?? [];
$reportForms = [];
foreach ($reportFields as $thisField) {
    $thisForm = $Proj->metadata[$thisField]['form_name'];
    if (isset($reportForms[$thisForm])) continue;
    $reportForms[$thisForm] = true;
}
$reportFormsAccess = array_intersect_key($user_rights['forms'], $reportForms);
// Use some user privileges and pagenum as additional salt for the cache
$cacheManager = CacheFactory::manager(PROJECT_ID);
$cacheOptions = [REDCapCache::OPTION_INVALIDATION_STRATEGIES => [ProjectActivityInvalidation::signature($project_id)]];
$cacheOptions[REDCapCache::OPTION_SALT] = [];
$cacheOptions[REDCapCache::OPTION_SALT][] = ['dag'=>$user_rights['group_id']];
if (isset($_GET['pagenum']) && isinteger($_GET['pagenum'])) {
    $cacheOptions[REDCapCache::OPTION_SALT][] = ['pagenum'=>$_GET['pagenum']];
}
$cacheOptions[REDCapCache::OPTION_SALT][] = ['public-report'=>(isset($_GET['__report']) ? 1 : 0)];
$reportFormsAccessSalt = [];
foreach ($reportFormsAccess as $thisForm=>$thisAccess) {
    $reportFormsAccessSalt[] = "$thisForm:$thisAccess";
}
$cacheOptions[REDCapCache::OPTION_SALT][] = ['form-rights'=>implode(",", $reportFormsAccessSalt)];
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
// Build dynamic filter options (if any)
$dynamic_filters = DataExport::displayReportDynamicFilterOptions($report_id);
// Obtain any dynamic filters selected from query string params
list ($liveFilterLogic, $liveFilterGroupId, $liveFilterEventId) = DataExport::buildReportDynamicFilterLogic($report_id);
// Total number of records queried
$totalRecordsQueried = $cacheManager->getOrSet([Records::class, 'getCountRecordEventPairs'], [], $cacheOptions);
// For report A and B, the number of results returned will always be the same as total records queried
if (($report_id == 'ALL' || ($report_id == 'SELECTED' && (!isset($_GET['events']) || empty($_GET['events']))))
	&& !isset($_GET['lf1']) && !isset($_GET['lf2']) && !isset($_GET['lf3']))
{
	if ($user_rights['group_id'] == '') {
		$num_results_returned = $totalRecordsQueried;
	} else {
		$num_results_returned = $cacheManager->getOrSet([Records::class, 'getCountRecordEventPairs'], [$user_rights['group_id']], $cacheOptions);
	}
}

// Cache buster --- useful for debugging 
// ALWAYS COMMENT OUT THIS LINE FOR PRODUCTION !!!!
// -----
// $cacheOptions[REDCapCache::OPTION_SALT][] = ['cache-buster' => time()];
// -----

// Get html report table
list ($report_table, $num_results_returned) = $cacheManager->getOrSet([DataExport::class, 'doReport'], [
	$report_id, 'report', 'html', false, false, false, false,
	false, false, false, false, false, false, false,
	(isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : array()),
	(isset($_GET['events']) ? explode(',', $_GET['events']) : array()),
	false, false, false, true, true, $liveFilterLogic, $liveFilterGroupId, $liveFilterEventId
], $cacheOptions);
$report_table = replaceUrlOldRedcapVersion($report_table);
// Report B only: If repeating instruments exist, and we're filtering using a repeating instrument, then the row counts can get skewed and be incorrect. This fixes it.
if ($report_id == 'SELECTED' && (!isset($_GET['events']) || empty($_GET['events'])) && !isset($_GET['lf1']) && !isset($_GET['lf2']) && !isset($_GET['lf3'])) {
	$totalRecordsQueried = $num_results_returned;
}
// Check report edit permission
$report_edit_access = defined("SUPER_USER") ? SUPER_USER : 0;
if ((!defined("SUPER_USER") || !SUPER_USER) && is_numeric($report_id)) {
    $reports_edit_access = DataExport::getReportsEditAccess(USERID, $user_rights['role_id'], $user_rights['group_id'], $report_id);
    $report_edit_access = in_array($report_id, $reports_edit_access);
}
$script_time_total = round(microtime(true) - $script_time_start, 1);

$downloadFilesBtnEnabled = ($user_rights['data_export_tool'] != '0' && DataExport::reportHasFileUploadFields($report_id, (isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : []), (isset($_GET['events']) ? explode(',', $_GET['events']) : [])));

// Add JS var to note that we just stored the page in the cache
if (!$cacheManager->hasCacheMiss()) {
    $cacheTime = CacheFactory::logger(PROJECT_ID)->getLastCacheTimeForProject(PROJECT_ID);
    if ($cacheTime != '') print RCView::hidden(['id'=>'redcap-cache-time', 'value'=>$cacheTime]);
}

if ($ai_services_enabled_global && $ai_datasummarization_service_enabled && $report_id != 'SELECTED') {
    $summary_details = AI::getReportAISummaryDetails($report_id);
    if (!empty($summary_details)) { // Summary already exists
        $show_view_summary = 1;
    } else { // Summary setup not yet created
        $show_view_summary = 0;
    }
    $div_options_html = "<div class='dropdown-menu' aria-labelledby='btnGroupDrop2'>
                            <a id='OpenAI-summary-setup' class='dropdown-item fs12 ' href='javascript:;' onclick='openAddFieldsForSummaryDialog(\"" . $report_id . "\"); return false;' style='padding-bottom:2px;color:#444;padding-left: 10px; display: " . ($show_view_summary == '1' ? 'none' : '') . ";'>
                                <i class='fas fa-plus me-1'></i>".RCView::tt('openai_066')."</a>
                            <a id='OpenAI-summary-editsetup' class='dropdown-item fs12 ' href='javascript:;' onclick='openAddFieldsForSummaryDialog(\"" . $report_id . "\"); return false;' style='padding-bottom:2px;color:#444;padding-left: 10px; display: " . ($show_view_summary == '1' ? '' : 'none') . ";'>
                                <i class='fas fa-pencil me-1'></i>".RCView::tt('openai_059')."</a>
                            <a id='OpenAI-summary' class='boldish dropdown-item fs12 " . ($show_view_summary == '1' ? '' : 'opacity35') . "' href='javascript:;' onclick='showOpenAISummaryResultDialog(\"" . $report_id . "\");' style='padding-bottom:2px;color:#d31d90;padding-left: 10px;" . ($show_view_summary == '1' ? '' : 'pointer-events:none;') . "'>
                                <i class='fas fa-list me-1'></i>".RCView::tt('openai_060')."</a>
                        </div>";

    $report = DataExport::getReports($report_id);
    $fieldsToSummarize = false;
	$fieldsToSummarizeExists = false;
    foreach ($report['fields'] as $this_field) {
        if ($fieldsToSummarize == false) {
            $attr = $Proj->metadata[$this_field];
            $element_type = $attr['element_type'];
            $validation_type = $attr['element_validation_type'];
            $isFreeFormTextField = (($element_type == 'text' && $validation_type == '') || $element_type == 'textarea');
            if (!$isFreeFormTextField || $this_field == $Proj->table_pk) continue;
            $fieldsToSummarizeExists = true; // Set it true when atleast one field found to summarize for this report
        }
    }

    $aiDetailsSet = AI::isServiceDetailsSet();
}
// Display report and title and other text
print  	"<div id='report_div' style='margin:0 0 20px;'>" .
			RCView::div(array('style'=>''),
				RCView::div(array('class'=>'report-results-returned', 'style'=>'float:left;width:350px;padding-bottom:5px;'),
					(isset($_GET['__report'])
						? RCView::div(array('class'=>'text-secondary fs12'),
							$lang['custom_reports_02'] .
							RCView::b(array('style'=>'margin-left:5px;'),
								User::number_format_user($num_results_returned)
							)
						)
						: RCView::div(array('class'=>'font-weight-bold'),
							$lang['custom_reports_02'] .
							RCView::span(array('style'=>'margin-left:5px;color:#800000;font-size:15px;'),
								User::number_format_user($num_results_returned)
							)
						)
					).
					(isset($_GET['__report']) ? "" :
						RCView::div(array(),
							$lang['custom_reports_03'] .
							RCView::span(array('id'=>'records_queried_count', 'style'=>'margin-left:5px;'),
								User::number_format_user($totalRecordsQueried)
							) .
							(!($longitudinal || $Proj->hasRepeatingFormsEvents()) ? "" :
								RCView::div(array('class'=>'fs11 mt-1', 'style'=>'color:#999;font-family:tahoma,arial;'),
									$lang['custom_reports_20']
								)
							)
						) .
						RCView::div(array('class'=>'fs11 mt-1 d-print-none', 'style'=>'color:#6f6f6f;'),
							$lang['custom_reports_19']." $script_time_total ".$lang['control_center_4469']
						)
					)
				) .
				RCView::div(array('class'=>'d-print-none', 'style'=>'float:left;'),
					// Buttons: Stats, Export, Print, Edit
					(isset($_GET['__report']) ?
						// Public report buttons
						RCView::div(array(),
							// Download Report Files
							(!($user_rights['data_export_tool'] != '0' && $downloadFilesBtnEnabled) ? '' :
								RCView::button(array('class'=>'hidden download-files-btn report_btn jqbuttonmed fs12 text-successrc', 'onclick'=>"window.location.href = '".APP_PATH_SURVEY_FULL."?__report={$_GET['__report']}&__passthru=".urlencode("DataExport/file_export_zip.php")."'+getLiveFilterUrl();"),
									'<i class="fa-solid fa-circle-down"></i> ' .$lang['report_builder_220']
								)
							)
						)
						:
						// Private report buttons
						RCView::div(array(),
							// Public link
							(!($report['is_public'] && $user_rights['user_rights'] == '1' && $GLOBALS['reports_allow_public'] > 0) ? "" :
								RCView::a(array('href'=>($report['short_url'] == "" ? APP_PATH_WEBROOT_FULL.'surveys/index.php?__report='.$report['hash'] : $report['short_url']), 'target'=>'_blank', 'class'=>'text-primary fs12 nowrap me-3 ms-1 align-middle'),
									'<i class="fas fa-link"></i> ' .$lang['dash_35']
								)
							) .
							// Stats & Charts button
							(!$user_rights['graphical'] || !$enable_plotting ? '' :
								RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id={$report_id}&stats_charts=1'+getInstrumentsListFromURL()+getLiveFilterUrl();", 'style'=>'color:#800000;font-size:12px;'),
									'<i class="fas fa-chart-bar"></i> ' .$lang['report_builder_78']
								) .
                                RCView::SP
							) .
							// Export Data button
							($user_rights['data_export_tool'] == '0' ? '' :
								RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"showExportFormatDialog('{$report_id}');", 'style'=>'color:#000066;font-size:12px;'),
									'<i class="fas fa-file-download"></i> ' .$lang['report_builder_48']
								)
							) .
              // Download Report Files
							(!($user_rights['data_export_tool'] != '0' && $downloadFilesBtnEnabled) ? '' :
								RCView::button(array('class'=>'hidden download-files-btn report_btn jqbuttonmed fs12 text-successrc', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/file_export_zip.php?pid=".PROJECT_ID."&report_id={$report_id}'+getInstrumentsListFromURL()+getLiveFilterUrl();"),
                                    '<i class="fa-solid fa-circle-down"></i> ' .$lang['report_builder_220']
                                ) .
                                RCView::SP
                            ) .
							// Print link
							RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"$('div.dataTables_scrollBody, div.dataTables_scrollHead').css('overflow','visible');$('.DTFC_Cloned').hide();setProjectFooterPosition();window.print();", 'style'=>'font-size:12px;'),
								RCView::img(array('src'=>'printer.png', 'style'=>'height:12px;width:12px;')) . $lang['custom_reports_13']
							) .
							RCView::SP .
							(($report_id == 'ALL' || $report_id == 'SELECTED' || !$user_rights['reports'] || (is_numeric($report_id) && !$report_edit_access)) ? '' :
								// Edit report link
								RCView::button(array('class'=>'report_btn jqbuttonmed', 'onclick'=>"window.location.href = '".APP_PATH_WEBROOT."DataExport/index.php?pid=".PROJECT_ID."&report_id={$report_id}&addedit=1';", 'style'=>'font-size:12px;'),
									'<i class="fas fa-pencil-alt fs10"></i> ' .$lang['custom_reports_14']
								)
							) .
                            (($ai_services_enabled_global && isset($aiDetailsSet) && $aiDetailsSet && $ai_datasummarization_service_enabled && $report_id != 'SELECTED' && $fieldsToSummarizeExists == true) ?
                                RCView::button(array('class'=>'report_btn jqbuttonmed dropdown-toggle ', 'data-bs-toggle' => 'dropdown', 'id' => 'btnGroupDrop1', 'style'=>'font-size:12px;color:#d31d90;'),
                                    '<i class="fas fa-wand-sparkles fs10"></i> '.RCView::tt('openai_057')
                                ) . $div_options_html : ''
                            )
						)
					) .
					// Dynamic filters (if any)
					$dynamic_filters
				) .
				RCView::div(array('class'=>'clear'), '')
			) .
			// Report title
			RCView::div(array('id'=>'this_report_title', 'style'=>'margin:10px 0 '.($report['description'] == '' ? '8' : '0').'px;padding:5px 3px;color:#800000;font-size:18px;font-weight:bold;'),
				// Title
				$report['title']
			) .
			// Report description (if has one)
			($report['description'] == '' ? '' : 
				RCView::div(array('id'=>'this_report_description', 'style'=>'max-width:1100px;padding:5px 3px;line-height:15px;'),
					Piping::replaceVariablesInLabel(filter_tags($report['description']))
				) .
				// Output the JavaScript to display all Smart Charts on the page
				Piping::outputSmartChartsJS()
			) .
			// Report table
			$report_table .
		"</div>";