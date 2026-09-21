<?php


require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

// Place all HTML here
$html = "";

// Ensure that report_id is numeric
if (isset($_GET['report_id']) && !(isinteger($_GET['report_id']) || $_GET['report_id'] == 'ALL' || $_GET['report_id'] == 'SELECTED')) unset($_GET['report_id']);

if (isset($_GET['report_id']) && (isinteger($_GET['report_id']))) {
    DataExport::checkReportHash($_GET['report_id']);
} else {
    DataExport::checkReportHash();
}
## CREATE NEW REPORT
if (isset($_GET['addedit']))
{
    // If user does not have ability Edit Reports, then stop here
    if (!$user_rights['reports']) {
        include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
        renderPageTitle();
        print  "<div class='red'>
                    <i class=\"fas fa-exclamation-circle\"></i> <b>{$lang['global_05']}</b><br><br> {$lang['config_02']} 
                    <a href=\"mailto:{$GLOBALS['project_contact_email']}\">{$GLOBALS['project_contact_name']}</a> {$lang['config_03']}
                </div>";
        include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
        exit;
    }
	// If user does not have EDIT ACCESS to this report, then go back to My Reports page
	if (isset($_GET['report_id']) && !SUPER_USER) {
		$reports_edit_access = DataExport::getReportsEditAccess(USERID, $user_rights['role_id'], $user_rights['group_id'], $_GET['report_id']);
		if (empty($reports_edit_access)) redirect(APP_PATH_WEBROOT . "DataExport/index.php?pid=" . PROJECT_ID);
	}
    // Make sure report belongs to project
    if (isset($_GET['report_id']) && DataExport::getProjectIdFromReportId($_GET['report_id']) !== PROJECT_ID) {
        redirect(APP_PATH_WEBROOT . "DataExport/index.php?pid=" . PROJECT_ID);
    }
	// Hidden dialog for help with filters and AND/OR logic
	$html .= DataExport::renderFilterHelpDialog();
	// Hidden dialog for error popup when field name entered is not valid
	$html .= RCView::div(array('id'=>'VarEnteredNoExist_dialog', 'class'=>'simpleDialog'), $lang['report_builder_72']);
	// Hidden dialog for longitudinal event-level filter checkbox
	$html .= RCView::div(array('id'=>'eventLevelFilter_dialog', 'class'=>'simpleDialog'),
				RCView::b($lang['data_export_tool_193']) . RCView::br() . 
                $lang['data_export_tool_313'] . RCView::br() . RCView::br() .
				$lang['data_export_tool_194'] . RCView::br() . RCView::br() .
				RCView::span(array('style'=>'color:#C00000;'), $lang['data_export_tool_195'])
			 );
	// Hidden dialog for "Quick Add" field dialog
	$html .= RCView::div(array('id'=>'quickAddField_dialog', 'class'=>'simpleDialog'), '&nbsp;');
	// Add the actual "create report" table's HTML at the very bottom since we're doing a direct print. So output the buffer and disable buffering.
	ob_start();ob_end_flush();
}
## OTHER EXPORT OPTIONS
elseif (isset($_GET['other_export_options']) && $user_rights['data_export_tool'] > 0)
{
	$html .= // Instructions
			RCView::p(array('style'=>'max-width:700px;margin:5px 0 10px;'),
				$lang['report_builder_116']
			) .
			// Get html for displaying additional export options
			DataExport::outputOtherExportOptions();
}
## VIEW LIST OF ALL REPORTS
elseif (!isset($_GET['report_id']))
{
	$html .= 	// Instructions
				RCView::p(array('style'=>'max-width:920px;margin:5px 0 15px;'),
					$lang['report_builder_117']
				) .
				// Report list table
				RCView::div(array('id'=>'report_list_parent_div'),
					DataExport::renderReportList()
				 );
	// Just in case, make sure that all report orders are correct
	DataExport::checkReportOrder();
}
## VIEW STATS & CHARTS
elseif (isset($_GET['stats_charts']) && isset($_GET['report_id'])
	&& (isinteger($_GET['report_id']) || in_array($_GET['report_id'], array('ALL', 'SELECTED'))))
{
    // Make sure report belongs to project
    if (isinteger($_GET['report_id']) && DataExport::getProjectIdFromReportId($_GET['report_id']) !== PROJECT_ID) {
        $html .= RCView::div(array('class'=>'red'),
            $lang['global_01'] . $lang['colon'] . " " . $lang['data_export_tool_180']
        );
    } else {
        // Get html for all the fields to display for report
        $html .= DataExport::outputStatsCharts(	$_GET['report_id'],
            (isset($_GET['instruments']) ? explode(',', $_GET['instruments']) : array()),
            (isset($_GET['events']) ? explode(',', $_GET['events']) : array()));
        // Add note about Missing Data Codes for "Missing" values
        $html .= RCView::div(array('class'=>'spacer mt-5'),' ') .
            RCView::h6(array('class'=>'mt-3', 'style'=>'color:#A00000;'),
                "<span class='em-ast' style='font-size:16px;'>*</span> " . $lang['missing_data_13']
            );
    }
}
## VIEW REPORT
elseif (isset($_GET['report_id']) && (isinteger($_GET['report_id']) || in_array($_GET['report_id'], array('ALL', 'SELECTED'))))
{
	// Get report name
	$report_name = DataExport::getReportNames($_GET['report_id'], !$user_rights['reports'], true, true, PROJECT_ID);
	// If report name is NULL, then user doesn't have Report Builder rights AND doesn't have access to this report
	if ($report_name === null) {
		$html .= RCView::div(array('class'=>'red'),
					$lang['global_01'] . $lang['colon'] . " " . $lang['data_export_tool_180']
				);
	} else {
		// Display progress while report loads via ajax
		$html .= DataExport::renderReportContainer($report_name);
	}
}


// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
$draft_preview_banner = "";
if (Design::isDraftPreview()) {
	$draft_preview_banner = "<div class='yellow draft-preview-banner mt-2 mb-2'>
		<i class='fa-solid fa-triangle-exclamation text-danger draft-preview-icon me-2'></i>" .
		RCView::lang_i("draft_preview_15", [
			"<a style='color:inherit !important;' href='".APP_PATH_WEBROOT."Design/online_designer.php?pid=".PROJECT_ID."'>",
			"</a>"
		], false) . "
	</div>";
}

print	RCView::div(array('style'=>'max-width:900px;margin-bottom:10px;'),
			RCView::div(array('style'=>'color: #800000;font-size: 16px;font-weight: bold;float:left;'),
				RCView::tt("app_23")
			) .
			RCView::div(array('class'=>'d-print-none', 'style'=>'float:right;padding:0 5px 5px 0;'),
				// VIDEO link
                '<i class="fas fa-film"></i> ' .
				RCView::a(array('href'=>'javascript:;', 'style'=>'font-size:12px;font-weight:normal;text-decoration:underline;', 'onclick'=>"popupvid('exports_reports01.mp4','".js_escape($lang['report_builder_131'])."');"),
					"{$lang['global_80']} {$lang['report_builder_131']}"
				)
			) .
			RCView::div(array('class'=>'clear'), '') .
			$draft_preview_banner
		);
addLangToJS(array(
	"global_53","openai_057","openai_057","global_53","bottom_90","openai_067","openai_060","bottom_90","openai_115","pub_089","design_243","design_121","report_builder_46","pub_085","design_338"
));

// JavaScript files
loadJS('Libraries/jquery_tablednd.js');
loadJS('Libraries/clipboard.js');
loadJS('DataExport.js');
loadJS('ReportView.js');
loadCSS('report.css');
// Hidden dialog to choose export format
$html .= DataExport::renderExportOptionDialog();
?>
<script type="text/javascript">
// Set variable if user has "reports" user rights
var user_rights_reports = <?php print $user_rights['reports'] ?>;
<?php if (isset($_GET['addedit'])) { ?>
	// List of field variables/labels for auto suggest
	var autoSuggestFieldList = <?php print DataExport::getAutoSuggestJsString() ?>;
	// List of all possible filter operators
	var allLimiterOper = new Object();
	<?php
	foreach (DataExport::getLimiterOperators() as $key=>$val) {
		// Change "not =" to "<>"
		if ($val == "not =") $val = "<>";
		print "allLimiterOper['$key'] = '".js_escape($val)."';\n";
	}
	?>
	// List of unique events
	var uniqueEvents = new Object();
	uniqueEvents[''] = '';
	<?php
	foreach ($Proj->getUniqueEventNames() as $key=>$val) {
		print "uniqueEvents['$key'] = '$val';\n";
	}
	?>
	// List of forms with comma-delimited list of fields in each form
	var formFields = new Object();
	<?php
	foreach ($Proj->forms as $key=>$val) {
		$formFields = $val['fields'];
		foreach (array_keys($formFields) as $this_field) {
			// Remove descriptive fields since they have no data
			if ($Proj->metadata[$this_field]['element_type'] == 'descriptive') {
				unset($formFields[$this_field]);
			}
		}
		print "formFields['$key']='".implode(',', array_keys($formFields))."';\n";
	}
	?>
	// List of fields with their respective form name
	var fieldForms = new Object();
	<?php
	foreach ($Proj->metadata as $this_field=>$attr) {
		print "fieldForms['$this_field']='{$attr['form_name']}';";
	}
	print "\nvar formLabels = new Object();\n";
	foreach ($Proj->forms as $key=>$attr) {
		print "formLabels['$key']='".js_escape(strip_tags(str_replace("\\", "", label_decode($attr['menu']))))."';";
	}
}
?>

// Language variables
var langQuestionMark = '<?php print js_escape($lang['questionmark']) ?>';
var closeBtnTxt = '<?php print js_escape($lang['global_53']) ?>';
var exportBtnTxt = '<?php print js_escape($lang['report_builder_48']) ?>';
var exportBtnTxt2 = '<?php print js_escape($lang['data_export_tool_199']." ".$lang['data_export_tool_209']) ?>';
var langSaveValidate = '<?php print js_escape($lang['report_builder_52']) ?>';
var langIconSaveProgress = '<?php print js_escape($lang['report_builder_55']) ?>';
var langIconSaveProgress2 = '<?php print js_escape($lang['report_builder_56']) ?>';
var langIconSaveProgress3 = '<?php print js_escape($lang['report_builder_147']) ?>';
var langNoTitle = '<?php print js_escape($lang['report_builder_68']) ?>';
var langNoUserAccessSelected = '<?php print js_escape($lang['report_builder_69']) ?>';
var langNoFieldsSelected = '<?php print js_escape($lang['report_builder_70']) ?>';
var langLimitersIncomplete = '<?php print js_escape($lang['report_builder_71']) ?>';
var langTypeVarName = '<?php print js_escape($lang['report_builder_30']) ?>';
var langDragReport = '<?php print js_escape($lang['report_builder_75']) ?>';
var langDelete = '<?php print js_escape($lang['global_19']) ?>';
var langDeleteReport = '<?php print js_escape($lang['report_builder_11']) ?>';
var langDeleteReportConfirm = '<?php print js_escape($lang['report_builder_76']) ?>';
var langCopy = '<?php print js_escape($lang['report_builder_46']) ?>';
var langCopyReport = '<?php print js_escape($lang['report_builder_08']) ?>';
var langCopyReportConfirm = '<?php print js_escape($lang['report_builder_77']) ?>';
var langExporting = '<?php print js_escape($lang['report_builder_51']) ?>';
var langConvertToAdvLogic = '<?php print js_escape($lang['report_builder_94']) ?>';
var langConvertToAdvLogic2 = '<?php print js_escape($lang['report_builder_95']) ?>';
var langConvertToAdvLogic3 = '<?php print js_escape($lang['report_builder_97']) ?>';
var langConvertToAdvLogic4 = '<?php print js_escape($lang['report_builder_98']) ?>';
var langConvertToAdvLogic5 = '<?php print js_escape($lang['report_builder_99']) ?>';
var langConvert = '<?php print js_escape($lang['report_builder_96']) ?>';
var langPreviewLogic = '<?php print js_escape($lang['report_builder_100']) ?>';
var langChooseOtherfield = '<?php print js_escape($lang['report_builder_103']) ?>';
var langError = '<?php print js_escape($lang['global_01']) ?>';
var langReportFailed = '<?php print js_escape($lang['report_builder_128']) ?>';
var langExportFailed = '<?php print js_escape($lang['report_builder_129']) ?>';
var langTotFldsSelected = '<?php print js_escape($lang['report_builder_138']) ?>';
var langExportWholeProject = '<?php print js_escape($lang['data_export_tool_208']) ?>';
var max_live_filters = <?php print DataExport::MAX_LIVE_FILTERS ?>;
var langCreateCustomLink = '<?php print js_escape($lang['report_builder_183']) ?>';
$(function(){
    initTinyMCEglobal('mceEditor');
});
</script>
<?php
// Tabs
DataExport::renderTabs();
// Output content
print $html;
// If displaying the "add/edit report" table, do direct Print to page because $html might get very big
if (isset($_GET['addedit'])) {
	DataExport::outputCreateReportTable(isset($_GET['report_id']) ? $_GET['report_id'] : '');
}
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';