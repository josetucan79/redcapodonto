<?php

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

use Vanderbilt\REDCap\Classes\MyCap\MyCap;
use Vanderbilt\REDCap\Classes\MyCap\ZeroDateTask;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTask;
use Vanderbilt\REDCap\Classes\MyCap\ActiveTasks;
use Vanderbilt\REDCap\Classes\MyCap\Task;
use Vanderbilt\REDCap\Classes\MyCap\Participant;
use Vanderbilt\REDCap\Classes\MyCap\Annotation;

$exportButtonDisabled = true;
$hasAutoInvitesDefined = false;
$surveyQueueExportEnabled = false;
$event_id = $event_id ?? null;

if ($surveys_enabled)
{
    $asi = new AutomatedSurveyInvitation(PROJECT_ID);
    $asi->listen();
    // get the scheduled ASI to check if the export button should be enabled
    $scheduledASI = $asi->getScheduledASI();
    $exportButtonDisabled = (count($scheduledASI) == 0);
    // get Survey Queue Setup (SQS)
    $sqs = new SurveyQueueSetup(PROJECT_ID);
    // survey queue export enabled implies survey queue enabled
    $surveyQueueExportEnabled = $sqs->isSurveyQueueExportEnabled();
    // Listen for SQS export/import requests and handle such requests.
    // Handling will stop further execution of this file.
    $sqs->listen();
}
// instantiate form display logic (FDL) setup instance
$fdl = new FormDisplayLogicSetup(PROJECT_ID);
// form display logic export enabled implies form display logic enabled
$formDisplayLogicExportEnabled = $fdl->isFormDisplayLogicEnabled();
// Check if descriptive popups are enabled
$dps = DescriptivePopup::getLinkTextAllPopups();
$descPopupsEnabled = !empty($dps);
// Listen for FDL export/import requests
// Handling of such a request may stop further execution of this file.
$fdl->listen();


// Validate PAGE
if (isset($_GET['page']) && $_GET['page'] != '' && (($status == 0 && !isset($Proj->forms[$_GET['page']])) || ($status > 0 && !isset($Proj->forms_temp[$_GET['page']])))) {
    if ($isAjax) {
        exit("ERROR!");
    } else {
        redirect(APP_PATH_WEBROOT . "index.php?pid=" . PROJECT_ID);
    }
}
// If attempting to edit a PROMIS CAT, which is not allowed, redirect back to Form list
list ($isPromisInstrument, $isAutoScoringInstrument) = PROMIS::isPromisInstrument(isset($_GET['page']) && $_GET['page'] != '' ? $_GET['page'] : '');
if (isset($_GET['page']) && $_GET['page'] != '' && $isPromisInstrument) {
    redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id");
}

// If attempting to edit a MyCap Active task instrument, which is not allowed, redirect back to Form list
if ($mycap_enabled && $mycap_enabled_global) {
    if (isset($_GET['page']) && $_GET['page'] != '' && isset($myCapProj->tasks[$_GET['page']]) && $myCapProj->tasks[$_GET['page']]['is_active_task'] == 1) {
        redirect(APP_PATH_WEBROOT . "Design/online_designer.php?pid=$project_id");
    }
}

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

#region Language strings
addLangToJS([
	"alerts_24",
	"calendar_popup_01",
	"colon",
	"create_project_20",
	"datatables_06",
	"design_57",
	"design_79",
    "design_128",
	"design_202",
	"design_203",
	"design_303",
	"design_304",
	"design_307",
	"design_315",
	"design_320",
	"design_321",
	"design_324",
	"design_330",
	"design_338",
	"design_392",
	"design_401",
	"design_411",
	"design_412",
	"design_414",
	"design_415",
	"design_416",
	"design_417",
	"design_418",
	"design_419",
	"design_420",
	"design_421",
	"design_422",
	"design_423",
	"design_425",
	"design_426",
	"design_427",
	"design_429",
	"design_441",
	"design_453",
	"design_496",
	"design_499",
	"design_500",
	"design_501",
	"design_525",
	"design_656",
	"design_729",
	"design_792",
	"design_829",
	"design_906",
	"design_908",
	"design_920",
	"design_921",
	"design_928",
	"design_929",
	"design_1056",
	"design_1057",
	"design_1058",
	"design_1059",
	"design_1060",
	"design_1061",
	"design_1062",
	"design_1081",
	"design_1116",
	"design_1124",
	"design_1125",
	"design_1126",
	"design_1127",
	"design_1129",
	"design_1130",
	"design_1131",
	"design_1132",
	"design_1133",
	"design_1144",
	"design_1145",
	"design_1146",
	"design_1147",
	"design_1148",
	"design_1149",
	"design_1150",
	"design_1157",
	"design_1158",
	"design_1159",
	"design_1166",
	"design_1167",
	"design_1168",
	"design_1172",
	"design_1180",
	"design_1186",
	"design_1209",
	"design_1210",
	"design_1218",
	"design_1222",
	"design_1223",
	"design_1224",
	"design_1225",
	"design_1226",
	"design_1227",
	"design_1228",
	"design_1229",
	"design_1230",
	"design_1231",
	"design_1248",
	"design_1249",
	"design_1250",
	"design_1251",
	"design_1252",
	"design_1253",
	"design_1254",
	"design_1255",
	"design_1256",
	"design_1257",
	"design_1258",
	"design_1268",
	"design_1275",
	"design_1276",
	"design_1277",
	"design_1278",
	"design_1280",
	"design_1281",
	"design_1282",
	"design_1283",
	"design_1292",
	"design_1293",
	"design_1295",
	"design_1296",
	"design_1298",
	"design_1299",
	"design_1300",
	"design_1302",
	"design_1303",
	"design_1304",
	"design_1305",
	"design_1309",
	"design_1312",
	"design_1313",
	"design_1314",
	"design_1315",
	"design_1316",
	"design_1317",
	"design_1318",
	"design_1323",
	"design_1325",
	"design_1326",
	"design_1328",
	"design_1333",
	"design_1334",
	"design_1339",
	"design_1340",
	"design_1343",
	"design_1344",
	"design_1345",
	"design_1346",
	"design_1351",
	"design_1352",
	"design_1359",
    "design_1366",
    "design_1367",
    "design_1371",
    "design_1381",
    "design_1393",
    "design_1394",
    "design_1397",
	"designate_forms_13",
	"designate_forms_21",
	"draft_preview_02",
	"draft_preview_20",
	"draft_preview_21",
	"draft_preview_03",
	"draft_preview_05",
	"draft_preview_01",
	"draft_preview_17",
	"draft_preview_18",
	"folders_11",
	"form_renderer_23",
	"global_01",
	"global_03",
	"global_19",
	"global_48",
	"global_53",
	"global_64",
	"global_79",
	"global_169",
	"mycap_mobile_app_92",
	"mycap_mobile_app_95",
	"mycap_mobile_app_108",
	"mycap_mobile_app_457",
	"mycap_mobile_app_458",
	"mycap_mobile_app_469",
	"mycap_mobile_app_470",
	"mycap_mobile_app_480",
	"mycap_mobile_app_481",
	"mycap_mobile_app_482",
	"mycap_mobile_app_594",
	"mycap_mobile_app_889",
	"mycap_mobile_app_843",
	"mycap_mobile_app_844",
	"random_02",
	"sqs_001",
	"survey_459",
	"survey_460",
	"survey_461",
	"survey_462",
	"survey_463",
	"survey_471",
	"survey_473",
	"survey_474",
	"survey_475",
	"survey_476",
	"survey_477",
	"survey_478",
    'mycap_mobile_app_884',
]);

if ($surveys_enabled) {
    ?>
    <script type="text/javascript">
      var langASI = {
        import_button: '<?php echo js_escape($lang['asi_001']) ?>',
        export_button: '<?php echo js_escape($lang['asi_002']) ?>',
        clone_description: '<?php echo js_escape($lang['asi_004']) ?>',
        export_help_description: '<?php echo js_escape($lang['asi_005'].RCView::a(array('href'=>'javascript:;', 'onclick'=>"$(this).hide();$('#asiImportFieldList').show();fitDialog($('#asiImportHelpDlg'));", 'style'=>'display:block;margin:10px 0;text-decoration:underline;'), $lang['asi_007']).RCView::ul(array('id'=>'asiImportFieldList', 'style'=>'font-size:11px;line-height:13px;color:#555;margin-top:10px;display:none;'), "<li>".implode("</li><li>", $asi->getHelpFieldsList())."</li>") ) ?>',
        selectAll: '<?php echo js_escape($lang['data_export_tool_52']) ?>',
        deselectAll: '<?php echo js_escape($lang['data_export_tool_53']) ?>',
        import_button1: '<?php echo js_escape($lang['global_53']) ?>',
        import_button2: '<?php echo js_escape($lang['asi_006']) ?>',
        save_button: '<?php echo js_escape($lang['designate_forms_13']) ?>',
        save_and_clone_button: '<?php echo js_escape($lang['asi_014']) ?>',
        from: '<?php echo js_escape($lang['global_37']) ?>',
        to: '<?php echo js_escape($lang['global_38']) ?>',
        asi_copied: '<?php echo js_escape($lang['asi_015']) ?>',
        asi_clone_title: '<?php echo js_escape($lang['asi_016']) ?>',
        asi_clone1: '<?php echo js_escape($lang['asi_017']) ?>',
        asi_clone2: '<?php echo js_escape($lang['asi_018']) ?>',
        asi_clone3: '<?php echo js_escape($lang['asi_019']) ?>',
        asi_upload1: '<?php echo js_escape($lang['asi_020']) ?>',
        asi_upload2: '<?php echo js_escape($lang['asi_021']) ?>',
        asi_reeval: '<?php echo js_escape($lang['asi_053']) ?>',
        asi_popup_title1: '<?php echo js_escape($lang['asi_054']) ?>',
        asi_popup_content1: '<?php echo js_escape($lang['asi_055']) ?>',
        asi_reset_accept_button: '<?php echo js_escape($lang['asi_056']) ?>',
        asi_reset_decline_button: '<?php echo js_escape($lang['asi_057']) ?>',
      };
      var langSQS = {
        import_help_description: '<?php echo js_escape($lang['sqs_002'].RCView::a(array('href'=>'javascript:;', 'onclick'=>"$(this).hide();$('#sqsImportFieldList').show();fitDialog($('#sqsImportHelpDlg'));", 'style'=>'display:block;margin:10px 0;text-decoration:underline;'), $lang['sqs_005']).RCView::ul(array('id'=>'sqsImportFieldList', 'style'=>'font-size:11px;line-height:13px;color:#555;margin-top:10px;display:none;'), "<li>".implode("</li><li>", $sqs->getHelpFieldsList())."</li>") ) ?>',
        import_button1: '<?php echo js_escape($lang['global_53']) ?>',
        import_button2: '<?php echo js_escape($lang['asi_006']) ?>',
        asi_upload1: '<?php echo js_escape($lang['sqs_003']) ?>',
        asi_upload2: '<?php echo js_escape($lang['sqs_004']) ?>'
      };
    </script>
    <?php
}
#endregion
print RCView::script("
	var onlineDesigner_moveIcon = '".RCIcon::OnlineDesignerMove()."';
	var onlineDesigner_choicesIcon = '".RCIcon::OnlineDesignerEditChoices()."';
");

// Load CSS
loadCSS("OnlineDesigner.css");
loadCSS("jspreadsheet.css");
loadCSS("jsuites.css");
if ($mycap_enabled) loadCSS("MyCap.css");

// Add popover to announce the Draft Preview feature
UIState::checkDisplayPopover('draft-preview-container', 'online-designer', 'draft-preview-announcement', RCView::tt('draft_preview_02',''), RCView::tt('design_1352',''));

// Shared Library flag to avoid duplicate loading is reset here for the user to load a form
$_SESSION['import_id'] = '';

//If project is in production, do not allow instant editing (draft the changes using metadata_temp table instead)
$metadata_table = ($status > 0 && $draft_mode > 0) ? "redcap_metadata_temp" : "redcap_metadata";
$ProjFields = ($status > 0 && $draft_mode > 0) ? $Proj->metadata_temp : $Proj->metadata;
$ProjForms = ($status > 0 && $draft_mode > 0) ? $Proj->forms_temp : $Proj->forms;


## AUTO PROD CHANGES (SUCCESS MESSAGE DIALOG)
if (isset($_GET['msg']) && $_GET['msg'] == "autochangessaved" && $auto_prod_changes > 0 && $status > 0 && $draft_mode == 0)
{
    // Set text to explain why changes were made automatically
    if ($auto_prod_changes == '1') {
        $explainText = $lang['design_279'];
    } elseif ($auto_prod_changes == '2') {
        $explainText = $lang['design_281'];
    } elseif ($auto_prod_changes == '3') {
        $explainText = $lang['design_288'];
    } elseif ($auto_prod_changes == '4') {
        $explainText = $lang['design_289'];
    }
    $explainText .= " " . $lang['design_282'];
    // Render hidden dialog div
    ?>
    <div id="autochangessaved" style="display:none;" title="<?php echo js_escape2($lang['design_276']) ?>">
        <div class="darkgreen" style="margin:20px 0;">
            <table cellspacing=8 width=100%>
                <tr>
                    <td valign="top" style="padding:15px 30px 0 20px;">
                        <img src="<?php echo APP_PATH_IMAGES ?>check_big.png">
                    </td>
                    <td valign="top" style="font-size:13px;font-family:verdana;padding-right:30px;">
                        <?php if (defined("AUTOMATE_ALL")) { ?>
                            <?php echo "<b>{$lang['global_79']} {$lang['design_277']}</b><br>{$lang['design_526']}" ?>
                        <?php } else { ?>
                            <?php echo "<b>{$lang['global_79']} {$lang['design_277']}</b><br>{$lang['design_280']}" ?>
                            <div style="padding:20px 0 0;">
                                <a href="javascript:;" onclick="$('#explainAutoChanges').toggle('fade');" style=""><?php echo $lang['design_278'] ?></a>
                            </div>
                        <?php } ?>
                    </td>
                </tr>
                <tr>
                    <td colspan="2">
                        <div style="display:none;margin-top:5px;border:1px solid #ccc;padding:8px;" id="explainAutoChanges"><?php echo $explainText ?></div>
                        <div class="fs11 yellow mt-3">
                            <?php echo RCView::tt($GLOBALS['new_form_default_prod_user_access'] == '0' ? 'design_991' : 'design_1003') ?>
                            <?php echo ($GLOBALS['new_form_default_prod_user_access'] == '2' ? RCView::tt('design_1009') : '') ?>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
        <div id="calcs_changed" class="yellow" style="<?php print ($_GET['calcs_changed'] != '1') ? "display:none;" : "" ?>margin:20px 0 0;">
            <img src="<?php echo APP_PATH_IMAGES ?>exclamation_orange.png">
            <?php echo RCView::b($lang['design_516']).RCView::br().$lang['design_517'] ?>
        </div>
        <div class="yellow" style="<?php print ($mycap_enabled_global && $mycap_enabled) ? "" : "display:none;" ?>margin:20px 0 0;">
            <img src='<?=APP_PATH_IMAGES?>mycap_logo_black.png' style='width:24px;position:relative;top:-2px;'>
            <?php echo $lang['mycap_mobile_app_677'] ?>
        </div>
    </div>
    <script type="text/javascript">
      $(function(){
        $('#autochangessaved').dialog({ bgiframe: true, modal: true, width: 750,
          buttons: { Close: function() {$(this).dialog('close'); } }
        });
      });
    </script>
    <?php
}

// TABS
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";

// Check if any notices need to be displayed regarding Draft Mode
include APP_PATH_DOCROOT . "Design/draft_mode_notice.php";

$sharedLibForms = '';

## VIDEO LINK AND SHARED LIBRARY LINK
// Share instruments to Shared Library (if in Prod and NOT in Draft Mode yet)
$sharedLibLink = "";
if ($shared_library_enabled && $draft_mode == 0 && $status > 0)
{
    // Create drop-down options
    $sharedLibForms = "";
    foreach ($Proj->forms as $form=>$attr) {
        $sharedLibForms .= "<option value='$form'>{$attr['menu']}";
        if (isset($formStyleVisible[$form])) {
            $sharedLibForms .= " " . $lang['shared_library_69'];
        }
        $sharedLibForms .= "</option>";
    }
    $sharedLibBtnDisabled = (($draft_mode == 0 || (isVanderbilt() && $super_user)) ? "" : "disabled");
    // Output link to page
    $sharedLibLink = RCView::div(array('style'=>'float:left;margin-top:4px;margin-right:30px;'),
        RCView::a(array('href'=>'javascript:;','style'=>'vertical-align:middle;text-decoration:underline;color:#3E72A8;','onclick'=>"\$('#shareToLibDiv').toggle('fade');"),
            RCView::i(['class'=>'far fa-question-circle me-1'], '') . $lang['setup_69'])
    );
}
// Display link(s)
$video_id = isset($_GET["page"]) && $_GET["page"] != "" ? "online_designer03.mp4" : "intro_instrument_dev.mp4";
print RCView::div(array('class'=>'clearfix mt-2 mb-3'),
    $sharedLibLink .
    RCView::div(array('style'=>'float:left;margin-top:4px;'),
        RCView::ConsortiumVideoLink(
            RCView::tt("design_02"), $video_id, $lang["training_res_101"]
        )
    ) .
    (!($status < 1 || ($status > 0 && $draft_mode == 1)) ? "" :
        RCView::div(array('style'=>'float:left;margin-left:90px;'),
            MetaData::renderDataDictionarySnapshotButton()
        )
    )
);


// Hidden div containing drop-down list of forms to share to Shared Library -->
print  "<div id='shareToLibDiv' style='display:none;max-width:700px;margin:20px 0;padding:8px;border:1px solid #ccc;background-color:#f5f5f5;'>
			<b>{$lang['setup_69']}</b><br>
			{$lang['setup_70']}
			<a href='javascript:;' style='text-decoration:underline;font-size:12px;' onclick=\"openLibInfoPopup('download')\">{$lang['design_250']}</a>
			<div style='padding:5px 0;'>
				<select id='form_names' class='x-form-text x-form-field notranslate' style=''>
					<option value=''>-- {$lang['shared_library_59']} --</option>
					$sharedLibForms
				</select>
				<button onclick=\"
					if ($('#form_names').val().length < 1){
						alert('Please select an instrument');
					} else {
						window.location.href = app_path_webroot+'SharedLibrary/index.php?pid='+pid+'&page='+$('#form_names').val();
					}
				\">{$lang['design_174']}</button>
			</div>
		</div>";

// 'READY TO ADD QUESTIONS' BOX: For single survey projects, if no questions have been added yet (or if the participant_id is hidden),
// then give big instructional box to get started.
if (isset($_GET['page']) && $_GET['page'] != "" && count($Proj->metadata) == 2 && $table_pk == "record_id")
{
    ?>
    <div id="ready_to_add_questions" class="green" style="max-width:780px;margin-top:20px;padding:10px 10px 15px;">
        <div style="text-align:center;font-size:20px;font-weight:bold;padding-bottom:5px;"><?php echo $lang['design_394'] ?></div>
        <div><?php echo $lang['design_393'] ?></div>
    </div>
    <p style="max-width:800px;"><?php echo $lang['design_07'] ?></p>
    <script type="text/javascript">
      $(function(){
        setTimeout(function(){
          $('#ready_to_add_questions').hide('blind',1500);
        },20000);
      });
    </script>
    <?php
}



//If user has not selected which form to edit, give them list of forms to choose from
loadJS('Libraries/jquery_tablednd.js');

// Version published success message box
print "<div class='versionPublishMsg darkgreen' style='max-width:600px;text-align:center; display:none;'><img src='".APP_PATH_IMAGES."tick.png'> ".$lang['mycap_mobile_app_93']." ".$lang['mycap_mobile_app_787']."</div>";
?>
    <!-- custom script -->
    <script type="text/javascript">
      // Language vars
      var form_moved_msg = (getParameterByName('page') == '')
        ? '<div style="color:green;font-size:13px;"><img src="'+app_path_images+'tick.png"> <?php echo js_escape($lang['design_371']) ?><br><br><?php echo js_escape($lang['design_373']) ?></div>'
        : '<?php echo js_escape($lang['design_372']) ?>';
      var langRecIdFldChanged = '<?php echo js_escape($lang['design_400']) ?>';
      var langQuestionMark = '<?php echo js_escape($lang['questionmark']) ?>';
      var langPeriod = '<?php echo js_escape($lang['period']) ?>';
      var langDelete = '<?php echo js_escape($lang['global_19']) ?>';
      var design_100 = '<?php echo js_escape($lang['design_100']) ?>';
      var design_99 = '<?php echo js_escape($lang['design_99']) ?>';
      var asi_024 = '<?php echo js_escape($lang['asi_024']) ?>';
      var asi_036 = '<?php echo js_escape($lang['asi_036']) ?>';
      var form_missing = '<?php echo js_escape($lang['design_988']) ?>';
      var logic_missing = '<?php echo js_escape($lang['design_989']) ?>';
      var duplicate_warning = '<?php echo js_escape($lang['design_971']) ?>';
      var confirm_msg = '<?php echo js_escape($lang['design_972']) ?>';
      var baseline_date_field = '<?php echo js_escape(ZeroDateTask::getBaselineDateField()) ?>';
      var mcFieldAnnotationsList = '<?php echo json_encode([Annotation::TASK_UUID, Annotation::TASK_STARTDATE, Annotation::TASK_ENDDATE, Annotation::TASK_SCHEDULEDATE]) ?>';
    </script>
<?php


// Publish new version help - hidden dialog
print RCView::simpleDialog($lang['mycap_mobile_app_96'].$lang['mycap_mobile_app_930'], $lang['mycap_mobile_app_92'], 'publishVersionDialog');



/**
 * CHOOSE A FORM TO EDIT OR ENTER NEW FORM TO CREATE
 */
if (!isset($_GET['page']) || $_GET['page'] == "")
{
    // If redirected here from Invite Participants when no surveys have been enabled yet, then display dialog for instructions
    // on how to enable surveys.
    if (isset($_GET['dialog']) && $_GET['dialog'] == 'enable_surveys')
    {
        ?>
        <script type="text/javascript">
          $(function(){
            simpleDialog('<?php echo js_escape(RCView::b($lang['global_03'].$lang['colon'])." ".$lang['survey_357']) ?>','<?php echo js_escape($lang['setup_84']) ?>','how_to_enable_surveys-dialog');
          });
        </script>
        <?php
    }

    // If user just created/edited the Survey Settings page, then give confirmation popup
    if (isset($_GET['survey_save']))
    {
        print 	RCView::div(array('id'=>'saveSurveyMsg','class'=>'darkgreen','style'=>'color:green;display:none;vertical-align:middle;text-align:center;padding:25px;font-size:15px;'),
            RCView::img(array('src'=>'tick.png')) . $lang['survey_1003']
        );
        ?>
        <script type="text/javascript">
          $(function(){
            // Change the URL in the browser's address bar to prevent reloading the msg if page gets reloaded
            modifyURL(window.location.protocol + '//' + window.location.host + window.location.pathname + '?pid=' + pid);
            // Display dialog
            simpleDialogAlt($('#saveSurveyMsg'), 2.2, 450);
          });
        </script>
        <?php
    }

    // If user just created/edited the Task Settings, then give confirmation popup
    if (isset($_GET['task_save']))
    {
        print 	RCView::div(array('id'=>'saveTaskMsg','class'=>'darkgreen','style'=>'color:green;display:none;vertical-align:middle;text-align:center;padding:25px;font-size:15px;'),
            RCView::img(array('src'=>'tick.png')) . $lang['mycap_mobile_app_131']
        );
        ?>
        <script type="text/javascript">
          $(function(){
            // Change the URL in the browser's address bar to prevent reloading the msg if page gets reloaded
            window.location.replace(window.location.protocol + '//' + window.location.host + window.location.pathname + '?pid=' + pid);
            //modifyURL(window.location.protocol + '//' + window.location.host + window.location.pathname + '?pid=' + pid);
            // Display dialog
            simpleDialogAlt($('#saveTaskMsg'), 2.2, 450);
          });
        </script>
        <?php
    }

    // Set flag if some parts of the instrument list table should be disabled to prevent editing because it's not in draft mode yet
    $disableTable = ($draft_mode != '1' && $status > 0);
    ?>
    <style type="text/css">
        .edit_saved  { background: #C1FFC1 url(<?php echo APP_PATH_IMAGES ?>tick.png) no-repeat right; }
        #forms_surveys .ftitle { padding-top: 2px; }
    </style>

    <!-- JS for Online Designer (Forms) -->
    <script type="text/javascript">
      // Set vars and functions
      var disable_instrument_table = <?php echo $disableTable ? 1 : 0 ?>;
      var numForms = <?php echo ($status < 1 ? $Proj->numForms : $Proj->numFormsTemp) ?>;
      // Function to give error message if try to click on form names when not editable
      function cannotEditForm() {
        simpleDialog('<?php echo js_escape($lang['design_374']) ?>','<?php echo js_escape($lang['design_375']) ?>');
      }
      // Function to give error message if try to click on fix mycap issues button when not editable
      function cannotFixMyCapIssues() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_760']) ?>','<?php echo js_escape($lang['mycap_mobile_app_761']) ?>');
      }
      // Function to give error message if try to click on fix mycap issues button when project is in draft mode and form is not yet reviewed
      function cannotFixMyCapIssuesBeforeReview() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_859']) ?>','<?php echo js_escape($lang['mycap_mobile_app_860']) ?>');
      }
      // Function to give error message if try to click on Adaptive form names, which are not editable
      function cannotEditAdaptiveForm() {
        simpleDialog('<?php echo js_escape($lang['design_508']) ?>','<?php echo js_escape($lang['design_507']) ?>');
      }
      // Function to give error message if try to click on Auto-Scoring form names, which are not editable
      function cannotEditAutoScoringForm() {
        simpleDialog('<?php echo js_escape($lang['data_entry_257']) ?>','<?php echo js_escape($lang['data_entry_256']) ?>');
      }
      // Function to give error message if try to click on PROMIS form names, which are not editable
      function cannotEditPromisForm() {
        simpleDialog('<?php echo js_escape($lang['design_779']) ?>','<?php echo js_escape($lang['design_778']) ?>');
      }
      // Function to give error message if try to click on Active Task form names, which are not editable
      function cannotEditActiveTaskForm() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_316']) ?>','<?php echo js_escape($lang['mycap_mobile_app_315']) ?>');
      }
      // Function to give error message if try to enable forms for MyCap to which baseline date field belongs
      function cannotEnableMyCap() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_471']) ?>','<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
      }
      // Function to give error message if try to enable PROMIS form which is not supported for MyCap
      function cannotEnablePromisForMyCap() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_488']) ?>','<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
      }
      // Function to give error message if try to enable Battery instrument and a first instrument in series already enabled
      function firstInstrumentEnabledForMyCap() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_865']) ?>','<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
      }
      // Function to give error message if try to enable Battery instrument and it is not a first instrument in series
      function firstInstrumentNeedToEnableForMyCap() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_864']) ?>','<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
      }
      // Function to give error message if try to enable form which is not utilizing any event (for Longitudinal projects only)
      function cannotEnableMyCapAsNoEvents(page) {
        var msg = '<div><div style="padding-top:5px;"><?php echo js_escape($lang['mycap_mobile_app_816']) ?></div>';
        msg += '<div style="padding-top:15px;"><a href="'+app_path_webroot+'Design/designate_forms.php?pid='+pid+'&page_edit='+page+'" style="text-decoration:underline;"><?php echo js_escape($lang['global_28']) ?></a></div></div>';

        simpleDialog(msg, '<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_723']) ?>');
      }
      // Function to give error message if try to enable form which has no fields other than MC annotations
      function cannotEnableMyCapAsNoFields() {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_727']) ?>','<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
      }
      // Function to give error message if try to enable instrument having errors for MyCap
      function cannotEnableErrorInstrumentForMyCap(errorDiv) {
        simpleDialog('<?php echo js_escape($lang['mycap_mobile_app_556']) ?><br>'+$("#"+errorDiv).html(),'<?php echo js_escape($lang['global_48'].$lang['colon']." ".$lang['mycap_mobile_app_352']) ?>');
      }
      // Language vars
      var langErrorColon = '<?php echo js_escape($lang['global_01'].$lang['colon']) ?>';
      var langDrag = '<?php echo js_escape($lang['design_366']) ?>';
      var langModSurvey = '<?php echo js_escape($lang['survey_315']) ?>';
      var langModMyCap = '<?php echo js_escape($lang['mycap_mobile_app_133']) ?>';
      var langClickRowMod = '<?php echo js_escape($lang['design_367']) ?>';
      var langAddNewFlds = '<?php echo js_escape($lang['design_368']) ?>';
      var langDownloadPdf = '<?php echo js_escape($lang['design_369']) ?>';
      var langAddInstHere = '<?php echo js_escape($lang['design_380']) ?>';
      var langNewInstName = '<?php echo js_escape($lang['design_381']) ?>';
      var langCreate = '<?php echo js_escape($lang['design_248']) ?>';
      var langYesDelete = '<?php echo js_escape($lang['design_397']) ?>';
      var langDeleteFormSuccess = '<?php echo js_escape($lang['design_398']) ?>';
      var langDeleted = '<?php echo js_escape($lang['create_project_102']) ?>';
      var langNotDeletedRand = '<?php echo js_escape($lang['design_399']) ?>';
      var langNo = '<?php echo js_escape($lang['design_99']) ?>';
      var langRemove2Bchar = '<?php echo js_escape($lang['design_79']) ?>';
      var langProvideInstName = '<?php echo js_escape($lang['design_382']) ?>';
      var langNewFormRights = '<?php echo js_escape($lang['design_956']) ?>';
      var langNewFormRights2 = '<?php echo js_escape($lang['global_79']) ?>';
      var langNewFormRights3 = '<?php echo js_escape($GLOBALS['new_form_default_prod_user_access'] == '0' ? $lang['design_957'] : $lang['design_1002']) ?>';
      var langInstrCannotBeginNum = '<?php echo js_escape($lang['design_383']) ?>';
      var langSetSurveyTitleAsForm1 = '<?php echo js_escape($lang['design_402']) ?>';
      var langSetSurveyTitleAsForm2 = '<?php echo js_escape($lang['design_403']) ?>';
      var langSetSurveyTitleAsForm3 = '<?php echo js_escape($lang['design_404']) ?>';
      var langSetSurveyTitleAsForm4 = '<?php echo js_escape($lang['design_405']) ?>';
      var langSetSurveyTitleAsForm5 = '<?php echo js_escape($lang['design_406']) ?>';
      var langSetSurveyTitleAsForm6 = '<?php echo js_escape($lang['design_407']) ?>';
      var langAutoInvite1 = '<?php echo js_escape($lang['design_408']) ?>';
      var langAutoInvite2 = '<?php echo js_escape($lang['design_409']) ?>';
      var langAutoInvite3 = '<?php echo js_escape($lang['design_410']) ?>';
      var langAutoInvite4 = '<?php echo js_escape($lang['email_users_01']) ?>';
      var langAutoInvite5 = '<?php echo js_escape($lang['survey_451']) ?>';
      var langAutoInvite6 = '<?php echo js_escape($lang['survey_452']) ?>';
      var langAutoInvite7 = '<?php echo js_escape($lang['survey_453']) ?>';
      var langAutoInvite8 = '<?php echo js_escape($lang['survey_454']) ?>';
      var langAutoInvite9 = '<?php echo js_escape($lang['survey_455']) ?>';
      var langAutoInvite10 = '<?php echo js_escape($lang['survey_456']) ?>';
      var langAutoInvite11 = '<?php echo js_escape($lang['survey_457']) ?>';
      var langAutoInvite12 = '<?php echo js_escape($lang['survey_458']) ?>';
      var langSurveyQueue1 = '<?php echo js_escape($lang['survey_545']) ?>';
      var langSurveyLogin1 = '<?php echo js_escape($lang['survey_610']) ?>';
      var langSurveyLogin2 = '<?php echo js_escape($lang['survey_611']) ?>';
      var langSurveyLogin3 = '<?php echo js_escape($lang['survey_612']) ?>';
      var langCannotDeleteForm = '<?php echo js_escape($lang['design_523']) ?>';
      var langCannotDeleteForm2 = '<?php echo js_escape($lang['design_524']) ?>';
      var langUploadInstZip1 = '<?php echo js_escape($lang['design_535']) ?>';
      var langUploadInstZip2 = '<?php echo js_escape($lang['design_537']) ?>';
      var langUploadInstZip3 = '<?php echo js_escape($lang['design_545']) ?>';
      var langUploadInstZip4 = '<?php echo js_escape($lang['design_546']) ?>';
      var langUploadInstZip5 = '<?php echo js_escape($lang['design_547']) ?>';
      var shared_lib_path = '<?php echo js_escape(SHARED_LIB_PATH) ?>';
      var langCopyInstr = '<?php echo js_escape($lang['design_556']) ?>';
      var langCopyInstr2 = '<?php echo js_escape($lang['design_562']) ?>';
      var langCopyInstr3 = '<?php echo js_escape($lang['design_563']) ?>';
      var langCopyInstr4 = '<?php echo js_escape($lang['design_564']) ?>';
      var langFDL1 = '<?php echo js_escape($lang['design_993']) ?>';
      var langFDL2 = '<?php echo js_escape($lang['design_994']) ?>';
      var langFDL3 = '<?php echo js_escape($lang['design_995']) ?>';
      var langFDL4 = '<?php echo js_escape($lang['design_1093']) ?>';
      var langAT01 = '<?php print js_escape($lang['mycap_mobile_app_185']) ?>';
      var langAT02 = '<?php print js_escape($lang['mycap_mobile_app_187']) ?>';
      var langCreateActiveTask = '<?php print js_escape($lang['mycap_mobile_app_190']) ?>';
      var langActiveTaskInstr = '<?php print js_escape($lang['design_382']) ?>';
      var langActiveTaskInstr1 = '<?php print js_escape($lang['mycap_mobile_app_183']) ?>';
      var langSetTaskTitleAsForm1 = '<?php echo js_escape($lang['mycap_mobile_app_330']) ?>';
      var langSetTaskTitleAsForm2 = '<?php echo js_escape($lang['mycap_mobile_app_331']) ?>';
      var langSetTaskTitleAsForm3 = '<?php echo js_escape($lang['mycap_mobile_app_332']) ?>';
      var langSetTaskTitleAsForm4 = '<?php echo js_escape($lang['mycap_mobile_app_333']) ?>';
      var langSetTaskTitleAsForm5 = '<?php echo js_escape($lang['mycap_mobile_app_334']) ?>';
      var langSetTaskTitleAsForm6 = '<?php echo js_escape($lang['mycap_mobile_app_335']) ?>';
      var langCannotDeleteBaselineDateForm = '<?php echo js_escape($lang['mycap_mobile_app_467']) ?>';
      var langCannotDeleteBaselineDateForm2 = '<?php echo js_escape($lang['mycap_mobile_app_468']) ?>';
      var langWarningTasksWithErrors = '<?php echo js_escape($lang['mycap_mobile_app_786']) ?>';
      var langImportATProcessText = '<?php echo js_escape($lang['mycap_mobile_app_886']) ?>';
      var langFDL = {
        import_button: '<?php echo js_escape($lang['fdl_002']) ?>',
        import_help_description: '<?php echo js_escape($lang['fdl_003'].RCView::a(array('href'=>'javascript:;', 'onclick'=>"$(this).hide();$('#sqsImportFieldList').show();fitDialog($('#sqsImportHelpDlg'));", 'style'=>'display:block;margin:10px 0;text-decoration:underline;'), $lang['fdl_004']).RCView::ul(array('id'=>'sqsImportFieldList', 'style'=>'font-size:11px;line-height:13px;color:#555;margin-top:10px;display:none;'), "<li>".implode("</li><li>", $fdl->getHelpFieldsList())."</li>") ) ?>',
        import_button1: '<?php echo js_escape($lang['global_53']) ?>',
        import_button2: '<?php echo js_escape($lang['asi_006']) ?>',
        fdl_upload1: '<?php echo js_escape($lang['fdl_005']) ?>',
        fdl_upload2: '<?php echo js_escape($lang['fdl_006']) ?>',
      };
    </script>
    <?php if ($surveys_enabled && !isset($_GET['form'])) {
    loadJS('SurveyQueueSetup.js');
    loadJS('AutomatedSurveyInvitationTool.js');
    loadJS('Libraries/handlebars.js');
}
    loadJS('FormDisplayLogicSetup.js');
    loadJS('DesignForms.js');
    loadJS("Libraries/jquery.repeater.min.js");

    $mtb_english_tasks_exists = $mtb_spanish_tasks_exists = false;
    if ($mycap_enabled && $mycap_enabled_global) {
        $activeTasks = ActiveTask::getActiveTasksListLayout('researchKit');
        if ($mtb_enabled) {
            // Execute a function which will add NEW *_item_count fields to existing MTB measures added to specific REDCap version
            ActiveTask::fixMissingFieldsMTBMeasures();
            $mtbActiveTasks = ActiveTask::getActiveTasksListLayout('mtb');
            $mtb_english_tasks_exists = ActiveTask::isLangMTBActiveTaskExists('English');
            $mtb_spanish_tasks_exists = ActiveTask::isLangMTBActiveTaskExists('Spanish');
        }
    }
    ?>

    <!-- INSTRUMENT ZIP FILE UPLOAD - DIALOG POP-UP -->
    <div id="zip-instrument-popup" title="<?php echo js_escape2($lang['design_535']) ?>" class="simpleDialog">
        <!-- Upload form -->
        <form id="zipInstrumentUploadForm" target="upload_target" enctype="multipart/form-data" method="post"
              action="<?php echo APP_PATH_WEBROOT ?>Design/zip_instrument_upload.php?pid=<?php echo $project_id ?>">
            <div style="font-size:13px;padding-bottom:15px;">
                <?php echo $lang['design_536'] ?>
                <a href="javascript:;" onclick="openZipInstrumentExplainPopup()" style="text-decoration:underline;"><?php echo $lang['design_548'] ?></a>
                <?php echo $lang['design_552'] ?>
            </div>
            <input type="file" id="myfile" name="myfile" style="font-size:13px;">
            <div style="font-size:11px;line-height:13px;padding-top:20px;color:#800000;">
                <?php echo $lang['design_567'] ?>
            </div>
        </form>
        <iframe style="width:0;height:0;border:0px solid #ffffff;" src="<?php echo APP_PATH_WEBROOT ?>DataEntry/empty.php" name="upload_target" id="upload_target"></iframe>
        <!-- Response message: Success -->
        <div id="div_zip_instrument_success" style="display:none;">
            <div style="font-weight:bold;font-size:14px;text-align:center;color:green;margin-bottom:20px;">
                <img src="<?php echo APP_PATH_IMAGES ?>tick.png">
                <?php echo $lang['design_200'] ?>
            </div>
            <?php echo $lang['design_540'] ?>
            <!-- Note about any duplicated fields -->
            <div id="div_zip_instrument_success_dups"></div>
            <!-- Note about MyCap settings uploaded -->
            <div id="div_zip_instrument_success_mycap"></div>
        </div>
        <!-- Response message: Failure -->
        <div id="div_zip_instrument_fail" style="display:none;font-weight:bold;font-size:14px;text-align:center;color:red;">
            <img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
            <?php echo $lang['design_137'] ?>
        </div>
        <!-- Upload in progress -->
        <div id="div_zip_instrument_in_progress" style="display:none;font-weight:bold;font-size:14px;text-align:center;">
            <?php echo $lang['data_entry_65'] ?><br>
            <img src="<?php echo APP_PATH_IMAGES ?>loader.gif">
        </div>
    </div>

    <!-- COPY INSTRUMENT - DIALOG POP-UP -->
    <div id="copy-instrument-popup" title="<?php echo js_escape2($lang['design_556']) ?>" class="simpleDialog">
        <div style="font-size:13px;">
            <?php echo $lang['design_557'] ?> "<b id="copy_instrument_label"></b>"<?php echo $lang['design_558'] ?>
        </div>
        <div style="font-size:13px;font-weight:bold;margin:15px 0 8px;">
            <div style="float:left;width:230px;padding:3px 10px 0 0;text-align:right;">
                <?php echo $lang['design_559'] ?>
            </div>
            <div style="float:left;">
                <input type="text" id="copy_instrument_new_name" class="x-form-text x-form-field" style="width:200px;">
            </div>
            <div style="clear:both;"></div>
        </div>
        <div style="font-size:13px;font-weight:bold;margin:8px 0 2px;">
            <div style="float:left;width:230px;padding:3px 10px 0 0;text-align:right;">
                <?php echo $lang['design_560'] ?>
            </div>
            <div style="float:left;">
                <input type="text" id="copy_instrument_affix" class="x-form-text x-form-field" style="width:60px;"
                       onblur="this.value = filterFieldAffix(this.value);">
            </div>
            <div style="clear:both;"></div>
        </div>
    </div>

    <!-- Active Tasks dialog -->
    <div id="activetask_list" style="display:none;">
        <div id="activeTasksContainer">
            <?php if ($mtb_enabled) { ?>
                <div style="margin:5px 2px 10px 2px;">
                    <?=RCView::tt('mycap_mobile_app_817'); ?>
                </div>
                <div id="sub-nav" class="d-print-none" style="margin:20px 0 15px 0;">
                    <ul>
                        <li class="active">
                            <a id="researchKitTasks" class="active-task-list-tab" href="javascript:;" style="font-size:13px; color:#393733; padding:6px 9px 5px 10px; border: none;">
                                <span style="vertical-align:middle">&nbsp;<?php echo RCView::tt('mycap_mobile_app_185'); ?></span></a>
                        </li>
                        <li>
                            <a id="mtbTasks" class="active-task-list-tab" href="javascript:;" style="font-size:13px; color:#393733; padding:6px 9px 5px 10px">
                                <span style="vertical-align:middle">&nbsp;Mobile Toolbox</span></a>
                        </li>
                    </ul>
                </div>
                <div id="List_researchKitTasks">
                    <div style="margin:5px 2px 10px 2px;">
                        <?=RCView::tt('mycap_mobile_app_965'); ?>
                    </div>
                    <div class="clear"></div>
                    <div class="row">
                        <?php echo $activeTasks;?>
                    </div>
                </div>
                <div id="List_mtbTasks" style="display: none;">
                    <div style="margin:5px 2px 10px 2px;">
                        <?=RCView::tt('mycap_mobile_app_855'); ?>
                    </div>
                    <div class="clear"></div>
                    <div style="margin:5px 2px 10px 2px;">
                        <?=RCView::tt('mycap_mobile_app_960'); ?>
                        <?php if ($mtb_english_tasks_exists && $mtb_spanish_tasks_exists) { ?>
                            <div style="float: right;">
                                <?php print RCView::a(array('href'=>'javascript:;', 'class'=>'fs12 ms-3 nowrap', 'style'=>'text-decoration:underline;', 'onclick'=>"simpleDialog('".RCView::tt('mycap_mobile_app_970')."', '".RCView::tt('mycap_mobile_app_969')."', null, 550);"), '<i class="fa-solid fa-mobile-screen-button fs13 mr-1" style="text-indent:0;position:relative;top:1px;"></i>'.$lang['mycap_mobile_app_969']) ?>
                            </div>
                        <?php } ?>
                    </div>
                    <div class="clear"></div>
                    <div class="row">
                        <?php echo $mtbActiveTasks;?>
                    </div>
                </div>
            <?php } else { ?>
                <div style="margin:5px 2px 10px 2px;">
                    <?=RCView::tt('mycap_mobile_app_964'); ?>
                </div>
                <div class="clear"></div>
                <div class="row">
                    <?php echo $activeTasks;?>
                </div>
            <?php } ?>
        </div>
    </div>

    <!-- CREATE ACTIVE TASK INSTRUMENT - DIALOG POP-UP -->
    <div id="activetask_add" title="<?php print RCView::tt_js2('mycap_mobile_app_187') ?>" class="simpleDialog">
        <div style="font-size:13px;">
            <?php print RCView::tt('mycap_mobile_app_188') ?> "<b id="activetask_instrument_label"></b>"<?php print RCView::tt('mycap_mobile_app_189') ?>
        </div>
        <div style="font-size:13px;font-weight:bold;margin:15px 0 8px;">
            <div style="float:left;width:230px;padding:3px 10px 0 0;text-align:right;">
                <?php print RCView::tt('design_559') ?>
            </div>
            <div style="float:left;">
                <input type="text" id="instrument_new_name" class="x-form-text x-form-field" style="width:200px;">
            </div>
            <div style="clear:both;"></div>
        </div>
    </div>

    <!-- Instructions -->
    <p style="margin-bottom:15px;max-width:<?=($surveys_enabled ? '920px' : '820px')?>;">
        <?php
        print "{$lang['design_377']} ";
        if ($status < 1) {
            print "{$lang['global_02']}{$lang['colon']} {$lang['design_27']}{$lang['period']}";
        } else {
            print ($draft_mode == '1' ? $lang['design_378'] : $lang['design_379']);
            if ($surveys_enabled) print " " . $lang['design_384'];
        }
        ?>
    </p>

    <?php

    // Check if event_id exists in URL. If not, then this is not "longitudinal" and has one event, so retrieve event_id.
    if (!$longitudinal && (!isset($_GET['event_id']) || $_GET['event_id'] == "" || !is_numeric($_GET['event_id'])))
    {
        $_GET['event_id'] = getSingleEvent($project_id);
    }

    $errorDivs = '';
    ## INSTRUMENT TABLE
    // Initialize vars
    $row_data = array();
    $stdmap_btn = ""; //default
    $row_num = 0; // loop counter
    // Create array of form_names that have automated invitations set for them (not checking more granular at event_id level)
    // Each form will have 0 and 1 subcategory to count number of active(1) and inactive(0) schedules for each.
    $formsWithAutomatedInvites = Design::formsWithAutomatedInvites();
    // Get array of PROMIS instrument names (if any forms were downloaded from the Shared Library)
    $promis_forms = PROMIS::getPromisInstruments();
    // Get array of AUTO-SCORING instrument names from Shared Library
    $auto_scoring_forms = PROMIS::getAutoScoringInstruments();
    // Get array of ADAPTIVE (CAT) instrument names from Shared Library
    $adaptive_forms = PROMIS::getAdaptiveInstruments();
    $batteryInstrumentsList = Task::batteryInstrumentsInSeriesPositions();
    // Display MyCap options
    $displayMyCapOptions = ($mycap_enabled && $mycap_enabled_global);
	// Query to get form names to display in table
	$sql = "select form_name, max(form_menu_description) as form_menu_description, count(1)-1 as field_count
			from $metadata_table where project_id = $project_id
			group by form_name order by field_order";
    $q = db_query($sql);
    // Loop through each instrument
    while ($row = db_fetch_assoc($q))
    {
        $row['form_menu_description'] = strip_tags(label_decode($row['form_menu_description']));
        // Give question mark if form menu name is somehow lost and set to ""
        if ($row['form_menu_description'] == "") $row['form_menu_description'] = "[ ? ]";
        // If survey exists, see if it's offline or active to determine the image to display
        $enabledSurveyChecked = "";
        if (isset($Proj->forms[$row['form_name']]['survey_id'])) {
            $enabledSurveyChecked = ($Proj->surveys[$Proj->forms[$row['form_name']]['survey_id']]['survey_enabled']) ? "checked" : "";
        }
        // Determine if instrument is a PROMIS form
        $isPromisForm = (in_array($row['form_name'], $promis_forms));
        // Determine if instrument is an auto-scoring form
        $isAutoScoringForm = ($isPromisForm && in_array($row['form_name'], $auto_scoring_forms));
        // Determine if instrument is an adaptive form
        $isAdaptiveForm = ($isPromisForm && in_array($row['form_name'], $adaptive_forms));
        // Show survey options (render but hide for all rows, then show only for first row)
        $enabledSurveyAutoContinue = (isset($Proj->forms[$row['form_name']]['survey_id']) && $Proj->surveys[$Proj->forms[$row['form_name']]['survey_id']]['end_survey_redirect_next_survey']);
        $enabledSurveyRepeat = (isset($Proj->forms[$row['form_name']]['survey_id']) && $Proj->surveys[$Proj->forms[$row['form_name']]['survey_id']]['repeat_survey_enabled'] && $Proj->isRepeatingFormAnyEvent($row['form_name']));
        $surveyHasStopActions = isset($Proj->forms[$row['form_name']]['survey_id']) && Survey::hasStopActions($Proj->forms[$row['form_name']]['survey_id']); 
        $enabledSurveyEConsent = isset($Proj->forms[$row['form_name']]['survey_id']) && Econsent::econsentEnabledForSurvey($Proj->forms[$row['form_name']]['survey_id']);
        // Link/button
        $enabledSurvey = (!isset($Proj->forms[$row['form_name']]['survey_id']))
            ? 	"<button class='btn btn-xs btn-defaultrc fs11' style='color:#0f7b0f;' onclick=\"window.location.href=app_path_webroot+'Surveys/create_survey.php?pid='+pid+'&view=showform&page={$row['form_name']}&redirectDesigner=1';\">{$lang['survey_152']}</button>"
            : RCView::a([
                    "class" => "enabled-as-survey-link",
                    "href" => APP_PATH_WEBROOT."Surveys/edit_info.php?pid=$project_id&view=showform&page={$row['form_name']}&redirectDesigner=1",
                ],
                RCView::span([ 
                        "class" => "enabled-as-survey-icons" . 
                        ($enabledSurveyRepeat ? " repeating" : "") . 
                        ($enabledSurveyAutoContinue ? " auto-continue" : "") . 
                        ($surveyHasStopActions ? " stop-action" : "") . 
                        ($enabledSurveyEConsent ? " econsent" : ""),
                    ],
                    RCIcon::Survey("fa-lg") .
                    RCView::span([
                            "class" => "indicator repeating",
                            "title" => js_escape($lang['design_701']),
                        ], RCIcon::SurveyRepeat()) .
                    RCView::span([
                            "class" => "indicator auto-continue",
                            "title" => js_escape($lang['design_655']),
                        ], RCIcon::SurveyAutoContinue()) .
                    RCView::span([
                            "class" => "indicator stop-action",
                            "title" => js_escape($lang['design_1378']),
                        ], RCIcon::SurveyStopAction()) .
                    RCView::span([
                            "class" => "indicator econsent",
                            "title" => js_escape($lang['design_1379']),
                        ], RCIcon::OnlineDesignerEConsent()) .
                    RCIcon::OnlineDesignerEdit("fa-sm edit show-on-hover")
                )
            );
        $modifySurveyBtn = (!isset($Proj->forms[$row['form_name']]['survey_id']))
            ? 	""
            : 	"<button class='btn btn-xs btn-defaultrc fs11 me-1 checkable $enabledSurveyChecked' onclick=\"window.location.href=app_path_webroot+'Surveys/edit_info.php?pid='+pid+'&view=showform&page={$row['form_name']}&redirectDesigner=1';\">{$lang['survey_314']}".RCView::fa('fa-solid fa-circle-check button-checked').RCView::fa('fa-solid fa-circle-xmark button-not-checked')."</button>";
        // AUTO INVITES BTN: Show button to define conditions for automated invitations (but only for surveys and not for first instrument)
        $defineSurveyConditionsBtn = "";
        if (isset($Proj->forms[$row['form_name']]['survey_id'])) {
            // Set event_id (set as 0 for longitudinal so we can prompt user to select event after clicking button here)
            $surveyCondBtnEventId = $Proj->longitudinal ? '0' : $Proj->firstEventId;
            // Set image of checkmark if already enabled
            $automatedInvitesEnabledImg = '';
            $automatedInvitesState = [];
            if (isset($formsWithAutomatedInvites[$row['form_name']])) {
                if ($formsWithAutomatedInvites[$row['form_name']]['1'] > 0) {
                    $automatedInvitesState["active"] = true;
                }
                if ($formsWithAutomatedInvites[$row['form_name']]['0'] > 0) {
                    $automatedInvitesState["inactive"] = true;
                }
            } else {
                $automatedInvitesEnabledImg = RCView::span(array('style'=>'margin-right:2px;'), "+");
            }
            switch (count($automatedInvitesState)) {
                case 1:
                    $automatedInvitesEnabledClass = isset($automatedInvitesState['active']) ? 'checked' : '';
                    break;
                case 2:
                    $automatedInvitesEnabledClass = 'checked-and-unchecked';
                    break;
                default:
                    $automatedInvitesEnabledClass = 'undetermined';
                    break;
            }
            // Warning about missing email field
            $noEmailWarning = "";
            if (isset($automatedInvitesState['active']) && $Proj->project["survey_email_participant_field"] == null && ($Proj->surveys[$Proj->forms[$row['form_name']]['survey_id']]['email_participant_field']??null) == null
                // Check to see if the initial survey email is being used in the participant list
				&& !Survey::usingInitialSurveyEmailsInPartList($Proj->project_id)
		    ) {
                $noEmailWarning = RCView::span([
                        "class" => "ms-1", 
                        "style" => "cursor: pointer;",
                        "title" => RCView::tt_attr("design_1385"),
                        "data-bs-toggle" => "tooltip",
                        "data-bs-placement" => "left",
                        "onclick" => "simpleDialog('".RCView::tt_js("design_1386")."', '".RCView::tt_js("design_1387")."', null, 600);",
                    ], 
                    RCIcon::ErrorNotificationTriangle("text-danger fa-xl")
                );
            }
            // Set button html
            $defineSurveyConditionsBtn = "<button id='autoInviteBtn-{$row['form_name']}' class='btn btn-xs btn-defaultrc fs11 me-1 checkable $automatedInvitesEnabledClass' onclick=\"setUpConditionalInvites({$Proj->forms[$row['form_name']]['survey_id']},$surveyCondBtnEventId,'{$row['form_name']}');\">{$automatedInvitesEnabledImg}{$lang['survey_342']}".RCView::fa('fa-solid fa-circle-check button-checked').RCView::fa('fa-solid fa-circle-xmark button-not-checked')."</button>" . $noEmailWarning;
        }
        // Invisible 'saved!' tag that only shows when update form order (dragged it)
        $saveMoveTag = "<span id='savedMove-{$row['form_name']}' style='display:none;margin-left:20px;color:red;'>{$lang['design_243']}</span>";
        // Invisible 'pencil/edit' icon to appear next to instrument name when mouseover
        $instrEditIcon = "<span class='instrEdtIcon' style='display:none;margin-left:6px;'>".RCIcon::OnlineDesignerEdit("fa-sm")."</span>";
        // Form actions drop-down list
        $formActionBtns =  	RCView::button(array('class'=>'formActionDropdownTrigger btn btn-xs btn-defaultrc fs11', 'onclick'=>"saveFormODrow('{$row['form_name']}',{$row['field_count']});showBtnDropdownList(this,event,'formActionDropdownDiv');"),
            $lang['design_554'] .
            RCView::img(array('src'=>'arrow_state_grey_expanded_sm.png', 'style'=>'margin-left:6px;position:relative;top:-1px;'))
        );
        // Add this form
        $row_data[$row_num][] = "<span style='display:none;'>{$row['form_name']}</span>";
        $repeating_indicator = $Proj->isRepeatingFormAnyEvent($row['form_name']) 
            ? RCView::span([
                "class" => "ms-2 badge badge-success fs8 info-item",
                "data-bs-toggle" => "tooltip",
                "title" => RCView::tt_attr("design_1391") // Instrument repeats on at least one event
            ], RCIcon::RepeatingIndicator())
            : "";
		$projTitleLink = "";
        if ($disableTable) {
            // Set label
            if ($isPromisForm) {
                $projTitleLink = RCView::span(array('style'=>'margin-left:10px;color:#999;font-size:11px;'),
                    ($isAutoScoringForm ? $lang['data_entry_255'] : ($isAdaptiveForm ? $lang['design_509'] : ""))
                );
            } else if ($mycap_enabled && $mycap_enabled_global) {
                if (isset($myCapProj->tasks[$row['form_name']]) && $myCapProj->tasks[$row['form_name']]['is_active_task'] == 1) {
                    $projTitleLink = RCView::span(array('id' => "formlabeladapt-{$row['form_name']}", 'style' => 'margin-left:10px;color:#999;font-size:11px;'),
                        "(".RCView::img(array('src'=>'tick_small_circle.png', 'style'=>'position:relative;top:-1px;')).(($myCapProj->tasks[$row['form_name']]['is_mtb_active_task'] == 1) ? (($myCapProj->tasks[$row['form_name']]['is_spanish_mtb_active_task'] == 1) ? RCView::tt('mycap_mobile_app_915') : RCView::tt('mycap_mobile_app_914')) : $lang['mycap_mobile_app_355']).")"
                    );
                }
            }
            // Display form name as simple text
            $row_data[$row_num][] = RCView::div(array('style'=>'font-size:12px;', 'onclick'=>"cannotEditForm()"),
                RCView::escape($row['form_menu_description']).$repeating_indicator.$projTitleLink . Design::getDesignateForEventLink(PROJECT_ID, $row['form_name'])
            );
        } else {
            // Set link
            if ($isPromisForm) {
                $projTitleLink = RCView::div(array('style'=>'font-size:13px;', 'onclick'=>($isAutoScoringForm ? "cannotEditAutoScoringForm()" : ($isAdaptiveForm ? "cannotEditAdaptiveForm()" : "cannotEditPromisForm()"))),
                    RCView::span(array('id'=>"formlabel-{$row['form_name']}"),
                        RCView::escape($row['form_menu_description'])
                    ) .
                    RCView::span(array('id'=>"formlabeladapt-{$row['form_name']}", 'style'=>'margin-left:10px;color:#999;font-size:11px;'),
                        ($isAutoScoringForm ? $lang['data_entry_255'] : ($isAdaptiveForm ? $lang['design_509'] : ""))
                    )
                );
            } else {
                $projTitleLink = "<a class='aGrid formLink' style='padding:3px;display:block;' href='".PAGE_FULL."?pid=$project_id&page={$row['form_name']}'"
                    . "><span id='formlabel-{$row['form_name']}'>{$row['form_menu_description']}</span>{$repeating_indicator}{$instrEditIcon}{$saveMoveTag}</a>";

                // Display "AT" label when project is not enabled for MyCap
                $myCapProjObj = new MyCap();
                $myCapProjObj->loadMyCapProjectValues();

                if (isset($myCapProjObj->tasks[$row['form_name']]) && $myCapProjObj->tasks[$row['form_name']]['is_active_task'] == 1) {
                    $projTitleLink = RCView::div(array('style' => 'font-size:13px;', 'onclick' => "cannotEditActiveTaskForm()"),
                        RCView::span(array('id' => "formlabel-{$row['form_name']}"),
                            RCView::escape($row['form_menu_description'])
                        ) . $repeating_indicator .
                        RCView::span(array('id' => "formlabeladapt-{$row['form_name']}", 'style' => 'margin-left:10px;color:#999;font-size:11px;'),
                            "(".RCView::img(array('src'=>'tick_small_circle.png', 'style'=>'position:relative;top:-1px;')).(($myCapProjObj->tasks[$row['form_name']]['is_mtb_active_task'] == 1) ? (($myCapProjObj->tasks[$row['form_name']]['is_spanish_mtb_active_task'] == 1) ? RCView::tt('mycap_mobile_app_915') : RCView::tt('mycap_mobile_app_914')) : $lang['mycap_mobile_app_355']).")"
                        )
                    );
                }
            }
            $designateForEvent = Design::getDesignateForEventLink(PROJECT_ID, $row['form_name']);
            $changeTaskTitle = 0;
            if (isset($myCapProj->tasks[$row['form_name']]['task_id'])) {
                $changeTaskTitle = 1;
            }

            // Display form name as link with hidden input for renaming
            $projTitleData = [
                'selector' => "[data-form-name=\"{$row['form_name']}\"]",
                'formName' => $row['form_name'],
                'displayName' => $row['form_menu_description'],
                'surveyTitle' => $Proj->getSurveyTitle($row['form_name']) ?? false,
                'taskTitle' => \Vanderbilt\REDCap\Classes\MyCap\Task::getTaskTitleByForm($project_id, $row["form_name"]),
                'canEditFormName' => $Proj->canEditFormName($row['form_name']),
                'offset' => '100, 10',
                'placement' => 'left',
            ];
            $row_data[$row_num][] = RCView::div([
                    "data-form-name" => $row['form_name'],
                    "data-form-info" => json_encode($projTitleData),
                ], 
                $projTitleLink . $designateForEvent
            );
        }
        $row_data[$row_num][] = $row['field_count'];
        $row_data[$row_num][] = "<a href='".APP_PATH_WEBROOT."index.php?route=PdfController:index&pid=$project_id&page={$row['form_name']}".(($status > 0 && $draft_mode == 1) ? "&draftmode=1" : "")."'><i class='fa-regular fa-file-pdf pdficon fs14' style='color:#B00000;'></i></a>";
        $mycap_access = $mycap_enabled && $mycap_enabled_global;
        // Display "enabled as survey" column
        if ($surveys_enabled) {
            if (($myCapProj->tasks[$row['form_name']]['is_active_task']??0) == 1) {
                $row_data[$row_num][] = "";
            } else {
                $row_data[$row_num][] = $enabledSurvey;
            }
        }

        if ($mycap_access) {
            $mycapEnabledInst = (isset($myCapProj->tasks[$row['form_name']]['redcap_instrument']));
	        $taskScheduleTitle = '';
            // $taskScheduleIcon = ($mycapEnabledInst) ? '<i class="fa-regular fa-calendar-days fs14" title="'.$myCapProj->tasks[$row['form_name']]['schedule_details'].'"></i> ' : '';
            if (!$Proj->longitudinal) {
                $schedules = Task::getTaskSchedules($myCapProj->tasks[$row['form_name']]['task_id'] ?? '');
                if (empty($schedules)) {
                    $mycapEnabledInst = false;
                }
                $taskScheduleTitle = ($mycapEnabledInst) ? $myCapProj->tasks[$row['form_name']]['schedule_details'] : '';
            }

            $form_oid = PROMIS::getPromisKey($row['form_name']);
            $list = ActiveTasks\Promis::unsupportedPromisInstruments();

            $isBatteryInstrument = false;
            // Check if Battery Instrument
            if (array_key_exists($row['form_name'], $batteryInstrumentsList)) {
                $isBatteryInstrument = true;
            }

            list ($issues, $warnings) = Task::checkErrors($row['form_name'], PROJECT_ID);

            $baselineDateFieldForm = ZeroDateTask::getBaselineDateForm();
            if (is_array($baselineDateFieldForm)) {
                $isBaselineDateForm = in_array($row['form_name'], $baselineDateFieldForm);
            } else {
                $isBaselineDateForm = ($baselineDateFieldForm == $row['form_name']);
            }
            $events = ($Proj->longitudinal) ? Task::getEventsList($row['form_name']) : array();

            // Get list of fields of instrument excluding fields having MyCap annotations
            $instrumentFields = Task::getListExcludingMyCapFields($row['form_name']);

            if ($isBaselineDateForm) {
                $myCapDisabled = true;
                $onClick = "onclick = \"cannotEnableMyCap()\"";
            } else if (empty($instrumentFields)) {
                $myCapDisabled = true;
                $onClick = "onclick = \"cannotEnableMyCapAsNoFields()\"";
            } else if ($isPromisForm && in_array($form_oid, $list)) {
                $myCapDisabled = true;
                $onClick = "onclick = \"cannotEnablePromisForMyCap()\"";
            } else if ($isBatteryInstrument == true && $batteryInstrumentsList[$row['form_name']]['batteryPosition'] > 1) {
                $myCapDisabled = true;
                $firstInstrument = $batteryInstrumentsList[$row['form_name']]['firstInstrument'];
                if (isset($myCapProj->tasks[$firstInstrument]['task_id'])) {
                    $onClick = "onclick = \"firstInstrumentEnabledForMyCap()\"";
                } else {
                    $onClick = "onclick = \"firstInstrumentNeedToEnableForMyCap()\"";
                }
            } else if ($Proj->longitudinal && empty($events)) {
                $myCapDisabled = true;
                $onClick = "onclick = \"cannotEnableMyCapAsNoEvents('".$row['form_name']."')\"";
            } else if (!empty($issues)) {
                $myCapDisabled = true;
                $errorDivs .= '<div style="display:none;" id="issues_'.$row['form_name'].'"><span class="error">'. implode("<br>", $issues) . '</span></div>';
                $onClick = "onclick = \"cannotEnableErrorInstrumentForMyCap('issues_".$row['form_name']."')\"";
            } else {
                $onClick = 'onclick="window.location.href=\''.APP_PATH_WEBROOT.'MyCap/create_task.php?pid='.PROJECT_ID.'&view=showform&page='.$row['form_name'].'&redirectDesigner=1\';"';
                $myCapDisabled = false;
            }
            // MyCap enabled column
            $taskScheduleLinkStyle = ($mycapEnabledInst) ? "right:3px;" : "";

            $mycapBtnChecked = '';
            if ($mycapEnabledInst) {
                $warningIcon = '';
                $myCapIssues = Task::getMyCapTaskErrors($row['form_name']);
                $myCapNonFixableErrors = Task::getMyCapTaskNonFixableErrors($row['form_name']);
                if ($myCapProj->tasks[$row['form_name']]['enabled_for_mycap'] == 0) {
                    $warningIcon = '<i class="fas fa-minus-circle" style="color:#C00000;" title="'.$lang['survey_433'].'"></i> ';
                } else {
                    if (!empty($myCapIssues) || !empty($myCapNonFixableErrors)) {
                        $onClickFix = "onclick = \"showMyCapIssues('" . $row['form_name'] . "'); return false;\"";
                        if (!empty($myCapIssues) && !empty($myCapNonFixableErrors)) {
                            $warningIcon = '<i class="fa fa-circle-exclamation" '.$onClickFix.' style="color:red;" title="'.$lang['mycap_mobile_app_728'].'"></i> ';
                        } else if (count($myCapIssues) > 0) {
                            $warningIcon = '<i class="fa fa-warning" '.$onClickFix.' style="color:darkorange;" title="'.$lang['mycap_mobile_app_729'].'"></i> ';
                        } else if (count($myCapNonFixableErrors) > 0) {
                            $warningIcon = '<i class="fa fa-circle-exclamation" '.$onClickFix.' style="color:red;" title="'.$lang['mycap_mobile_app_730'].'"></i> ';
                        }
                    } else {
                        $warningIcon = '<img src="'.APP_PATH_IMAGES.'tick_small2.png" style="position:relative;top:-1px;"> ';
                        $mycapBtnChecked = 'checked';
                    }
                }
            }

            $enabledMyCap = !$mycapEnabledInst
                ? 	($row['form_name'] == $Proj->firstForm ? "" : "<button class='btn btn-xs btn-defaultrc fs11 ".($myCapDisabled ? "opacity35" : "")."' style='color:#000066;' ".$myCapDisabled." ".$onClick.">{$lang['survey_152']}</button>")
                :	"<a class='modmycapstg' href='".APP_PATH_WEBROOT."MyCap/edit_task.php?pid=$project_id&view=showform&page={$row['form_name']}&redirectDesigner=1' style='display:block;text-align:center;position:relative;'>{$warningIcon}<img src='".APP_PATH_IMAGES."mycap_logo_black.png' style='width:24px;position:relative;top:-2px;' title='".js_escape($taskScheduleTitle)."'></a>";
            $row_data[$row_num][] = $enabledMyCap;
        }
        // Instrument actions column
        $row_data[$row_num][] = "<span class='formActions'>
									$formActionBtns
									$stdmap_btn
								 </span>";
        // Display survey-related options
        $surveyMyCapOptions = "";
        if ($displayMyCapOptions && $mycapEnabledInst) {
            $surveyMyCapOptions .= "<button class='btn btn-xs btn-defaultrc fs11 me-1 checkable $mycapBtnChecked' onclick=\"window.location.href=app_path_webroot+'MyCap/edit_task.php?pid=$project_id&view=showform&page={$row['form_name']}&redirectDesigner=1';\">".
                RCView::tt("mycap_mobile_app_637").RCIcon::CheckMarkCircle("button-checked").RCIcon::CrossCircle("button-not-checked")."
			</button>";
        }
        if ($surveys_enabled) {
            $surveyMyCapOptions = "<span id='{$row['form_name']}-btns' class='formActions'>
										$modifySurveyBtn
										$defineSurveyConditionsBtn
									 </span>".$surveyMyCapOptions;
        }
        if ($surveys_enabled || $displayMyCapOptions) {
            $row_data[$row_num][] = $surveyMyCapOptions;
        }
        // Increment counter
        $row_num++;
    }

    // Set table headers and attributes
    $col_widths_headers = array();
    $col_widths_headers[] = array(15, "", "center");
    $col_widths_headers[] = array(($surveys_enabled ? 382 : 580), RCView::SP . RCView::b($lang['design_244']));
    $col_widths_headers[] = array(34,  $lang['home_32'], "center");
    $col_widths_headers[] = array(29,  RCView::div(array('class'=>'wrap', 'style'=>'line-height:12px;padding:2px 0;'), $lang['global_85']), "center");
    if ($surveys_enabled) {
        $col_widths_headers[] = array(62, RCView::div(array('style'=>'line-height:12px;padding:2px 0;'), $lang['design_365'].RCView::br().$lang['global_59']), "center");
    }
    if ($displayMyCapOptions) {
        $col_widths_headers[] = array(67, RCView::div(array('class'=>'wrap', 'style'=>'line-height:12px;padding:2px 0;'), $lang['mycap_mobile_app_100']), "center");
    }
    $col_widths_headers[] = array(106, RCView::div(array('style'=>'line-height:12px;padding:2px 0;'), $lang['design_389']), "center");

    if ($surveys_enabled && $displayMyCapOptions) {
        $col_widths_headers[] = array(380, $lang['mycap_mobile_app_636']);
    } elseif ($surveys_enabled && !$displayMyCapOptions) {
        $col_widths_headers[] = array(350, $lang['design_390']);
    } elseif (!$surveys_enabled && $displayMyCapOptions) {
        $col_widths_headers[] = array(350, $lang['mycap_mobile_app_635']);
    }

    // Set table width
    // $instTableWidth = ($surveys_enabled || ($mycap_enabled && $mycap_enabled_global)) ? 1127 : 830;
    $instTableWidth = 830;
    if ($surveys_enabled && $displayMyCapOptions) {
        $instTableWidth = 1150;
    } elseif ($surveys_enabled && !$displayMyCapOptions) {
        $instTableWidth = 1040;
    } elseif (!$surveys_enabled && $displayMyCapOptions) {
        $instTableWidth = 1050;
    }

    if ($surveys_enabled)
    {
        // If survey notifications are enabled, then display the check icon for the survey queue button
        $survey_notifications_active_style = (Survey::surveyNotificationsEnabled()) ? '' : 'display:none;';
        // If survey login is enabled, then display the check icon for the survey login button
        $survey_login_button_checked = Survey::surveyLoginEnabled() ? "checked" : "";
        // Determine if an ASIs have been set
        $surveyScheduler = new SurveyScheduler(PROJECT_ID);
        $surveyScheduler->setSchedules();
        $hasAutoInvitesDefined = !empty($surveyScheduler->schedules);
    }
    if ($mycap_access) {
        loadJS('MyCapProject.js');
        $baselineDateEnabled = ZeroDateTask::baselineDateEnabled();

        $taskErrors = Task::getMyCapTaskErrors('');
        $taskNonFixableErrors = Task::getMyCapTaskNonFixableErrors('');
    }
    $total = 0;
    if (is_array($myCapProj->tasks ?? null)) {
        foreach ($myCapProj->tasks as $task) {
            $taskWithNonFixableErrors = Task::getMyCapTaskNonFixableErrors($task['redcap_instrument']);
            // Ignore tasks having non-fixable errors
            if (empty($taskWithNonFixableErrors) && $task['enabled_for_mycap'] == 1) {
                $total++;
            }
        }
    }
    $text_or_link = $mtb_enabled ? RCView::tt('mycap_mobile_app_185')
        : "<a href='https://projectmycap.org/wp-content/uploads/2025/04/Active-Tasks-Table.pdf' target='_blank' class='fs11' style='text-decoration:underline;'>".RCView::tt('mycap_mobile_app_885')."</a>";

    $ps = new PdfSnapshot();
    $pdfSnapshotsEnabled = $ps->numSnapshotsEnabled(PROJECT_ID) > 0;
    $pdfSnapshotsChecked = $pdfSnapshotsEnabled ? 'checked' : '';
    $ec = new Econsent();
    $econsentEnabled = !empty($ec->getEconsentSettings(PROJECT_ID, true));
    $econsentChecked = $econsentEnabled ? 'checked' : '';

    // Set table title display
    $instTableTitle = " <div class='clearfix' style='color:#333;'>                        
                            <div class='float-start wrap' style='width:465px;border-right:1px solid #ccc;font-weight:normal;'>
                                <div class='fs15 m-2 font-weight-bold'>
                                    {$lang['global_36']} <!-- Data Collection Instruments -->
                                </div>
                                <!-- Add new instrument -->
                                <div class='ms-2 pt-2' style='border-top:1px solid #ccc;visibility:" . ($disableTable ? "hidden" : "visible") . ";'>
                                    <div class='fs12' style='padding:1px 0 2px;'>
                                        <button class='btn btn-xs btn-defaultrc fs11 text-successrc' style='vertical-align:middle;' onclick=\"showAddForm();\"><i class=\"fas fa-plus\" style='margin-right:2px;'></i> {$lang['design_248']}</button>
                                        <span style='vertical-align:middle;margin-left:2px;'>{$lang['design_249']}</span>
                                    </div>
                                    <div class='fs12' style='padding:1px 0 2px;display:" . ($shared_library_enabled ? "block" : "none") . ";'>
                                        ".SharedLibrary::renderBrowseLibraryForm()."
                                        <button class='btn btn-xs btn-defaultrc fs11' style='vertical-align:middle;color:#00529f;' onclick=\"$('form#browse_rsl').submit();\"><i class=\"fas fa-file-import fs12\" style='margin-right:1px;'></i> {$lang['design_551']}</button>
                                        <span style='vertical-align:middle;'>{$lang['design_534']}</span>
                                        <a href='javascript:;' onclick=\"openLibInfoPopup('download')\" style='font-size:11px;text-decoration:underline;vertical-align:middle;'>{$lang['shared_library_57']}</a>
                                    </div>
                                    ".(!(Files::hasZipArchive()) ? "" :
            "<div class='fs12' style='padding:1px 0 2px;" . (!$shared_library_enabled && !$mycap_access ? "margin-bottom:25px;" : "") . "'>
                                            <button class='btn btn-xs btn-defaultrc fs11' style='vertical-align:middle;color:#A86700;' onclick=\"openZipInstrumentPopup()\"><i class=\"fas fa-upload\"></i> {$lang['design_530']}</button>
                                            <span style='vertical-align:middle;'>{$lang['design_531']}</span>
                                            <a href='javascript:;' onclick=\"openZipInstrumentExplainPopup()\" style='font-size:11px;text-decoration:underline;vertical-align:middle;'>{$lang['design_533']}</a>
                                        </div>"
        ).
        (!$mycap_access ? "" :
            "<div class='fs12' style='padding:1px 0 2px;'>
                                            <button class='btn btn-xs btn-defaultrc fs11 text-successrc' style='vertical-align:middle;' onclick=\"openActiveTasksListing();\"><i class=\"fas fa-plus\" style='margin-right:2px;'></i> {$lang['mycap_mobile_app_190']}</button>
                                            <span style='vertical-align:middle;margin-left:2px;'>
                                                ".RCView::tt('mycap_mobile_app_191')." ".
            $text_or_link."
                                            </span>
                                        </div>"
        )."
                                </div>
                            </div>  
                            
                            <div class=''>
                            
                                <!-- Form Options -->
                                <div class='float-start wrap' style='width:160px;padding:8px 5px 0px 10px;color:#444;'>
                                    <div class='boldish mb-1'>{$lang['design_986']}</div>
									<button type='button' style='color:#0f7b0f;position:relative;' class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 $pdfSnapshotsChecked' onclick=\"window.location.href=app_path_webroot+'index.php?pid=".PROJECT_ID."&route=PdfSnapshotController:index';\">".RCIcon::OnlineDesignerPDFSnapshot("me-1").RCView::tt("econsent_187")." ".RCView::fa('fa-solid fa-circle-check button-checked')."</button>
                                    <div id='FDL-container' class='btn-group dropdown' role='group'>
                                        <button id='btnGroupDrop0' type='button' class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 dropdown-toggle checkable ".($formDisplayLogicExportEnabled ? "checked" : "")."' onclick='toggleFormDisplayLogicSetupExport()' style='padding-top:1px;padding-left: 6px;color:#800000;' data-bs-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>".RCIcon::OnlineDesignerFDL("me-1").RCView::tt("design_985").RCIcon::CheckMarkCircle("button-checked")."
                                        </button>
                                        <div class='dropdown-menu' aria-labelledby='btnGroupDrop2'>
                                            <a id='FDL-container_dropdown_setup' class='dropdown-item fs12 ' href='javascript:;' onclick='displayFormDisplayLogicPopup()' style='padding-bottom:2px;color:#8A5502;padding-left: 10px;'>".RCIcon::OnlineDesignerEdit("fa-xs me-1").RCView::tt("fdl_000")."</a>
                                            <a class='dropdown-item fs12 ' href='javascript:;' onclick='FormDisplayLogicSetup.showImportHelp()' style='padding-bottom:2px;color:#000066;padding-left: 10px;'>".RCIcon::OnlineDesignerUpload("fa-xs me-1").RCView::tt("fdl_002")."</a>
                                            <a id='FDL-container_dropdown_export' class='dropdown-item fs12 opacity35' href='javascript:;' onclick='' style='padding-bottom:2px;padding-left: 10px;'>".RCIcon::OnlineDesignerDownload("fa-xs me-1").RCView::tt("fdl_001")."</a>
                                        </div>
                                    </div>
									<button class=\"nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1\" style=\"color:#a00000;\" onclick=\"window.location.href=app_path_webroot+'index.php?route=PdfController:index&pid=".PROJECT_ID."&all';\" title=\"".RCView::tt_attr("design_266")."\" data-bs-toggle=\"tooltip\">".RCIcon::OnlineDesignerPDF("me-1 fs12").RCView::tt("global_301")."</button>
									<button class=\"nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 checkable ".($descPopupsEnabled ? "checked" : "")."\" style=\"padding: 1px 8px 1px;\" onclick=\"window.location.href=app_path_webroot+'Design/descriptive_popups.php?pid=".PROJECT_ID."';\">".RCView::fa("fa-regular fa-comment-dots")." ".RCView::tt("descriptive_popups_01")." ".RCView::fa('fa-solid fa-circle-check button-checked')."</button>
                                </div>".
        (!$surveys_enabled ? '' : "
                                <!-- Survey Options -->
                                <div class='float-start wrap' style='min-width:300px;max-width:400px;padding:8px 5px 0px 10px;color:#444;'> 
									<div class='boldish mb-1'>".RCView::tt("survey_549")."</div>".
            (!$pdf_econsent_system_enabled ? "" : "
									<button type='button' style='color:#0f7b0f;position:relative;' class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 $econsentChecked' onclick=\"window.location.href=app_path_webroot+'index.php?pid=".PROJECT_ID."&route=EconsentController:index';\">".RCIcon::OnlineDesignerEConsent("me-1").RCView::tt("econsent_188").RCView::fa('fa-solid fa-circle-check button-checked')."</button>")."
                                    <div id='SQS-container' class='btn-group dropdown' role='group'>
                                        <button id='btnGroupDrop1' type='button' class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 dropdown-toggle checkable ".($surveyQueueExportEnabled ? "checked" : "")."' onclick='toggleSurveyQueueExport()' style='padding-top:1px;padding-left: 6px;color:#800000;' data-bs-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>".RCIcon::OnlineDesignerSurveyQueue("me-1").RCView::tt("survey_505").RCIcon::CheckMarkCircle("button-checked")."
                                        </button>
                                        <div class='dropdown-menu' aria-labelledby='btnGroupDrop2'>
                                            <a id='SQS-container_dropdown_setup' class='dropdown-item fs12 ' href='javascript:;' onclick='displaySurveyQueueSetupPopup()' style='padding-bottom:2px;color:#8A5502;padding-left: 10px;'>
												".RCIcon::OnlineDesignerEdit("fa-xs me-1").RCView::tt("survey_1581")."
											</a>
                                            <a class='dropdown-item fs12 ' href='javascript:;' onclick='SurveyQueueSetup.showImportHelp();' style='padding-bottom:2px;color:#000066;padding-left: 10px;'>
												".RCIcon::OnlineDesignerUpload("fa-xs me-1").RCView::tt("sqs_001")."
											</a>
                                            <a id='SQS-container_dropdown_export' class='dropdown-item fs12 disabled' href='javascript:;' onclick='SurveyQueueSetup.export();' style='padding-bottom:2px;padding-left: 10px;'>
												".RCIcon::OnlineDesignerDownload("fa-xs me-1").RCView::tt("sqs_007")."
											</a>
                                        </div>
                                    </div>
                                    <div id='ASI-container' class='btn-group dropdown' role='group'>
                                        <button id='btnGroupDrop2' type='button' class='nowrap btn btn-defaultrc btn-xs dropdown-toggle fs11 ms-1 mb-1' style='padding-top:1px;padding-left: 6px;' data-bs-toggle='dropdown' aria-haspopup='true' aria-expanded='false'>
											".RCIcon::OnlineDesignerAutoInvitationOptions("me-1").RCView::tt("asi_051")."
                                        </button>
                                        <div class='dropdown-menu' aria-labelledby='btnGroupDrop2'>
                                            <a class='dropdown-item fs12 ' href='javascript:;' onclick='AutomatedSurveyInvitationTool.showExportHelp();' style='padding-bottom:2px;color:#8A5502;padding-left: 10px;'>".RCIcon::OnlineDesignerUpload("fa-xs me-1").RCView::tt("asi_001")."</a>
                                            <a class='dropdown-item fs12 ".($exportButtonDisabled ? "disabled" : "")."' href='javascript:;' onclick='AutomatedSurveyInvitationTool.export();' style='padding-bottom:2px;padding-left: 10px;'>".RCIcon::OnlineDesignerDownload("fa-xs me-1").RCView::tt("asi_002")."</a>
                                            <a class='dropdown-item fs12 ".(($hasAutoInvitesDefined && Records::getRecordCount(PROJECT_ID) > 0) ? "" : "disabled")."' href='javascript:;' onclick='dialogReevalAutoInvites();' style='padding-bottom:2px;padding-left: 10px;'>".RCIcon::OnlineDesignerReevaluateASIs("me-1").RCView::tt("asi_052")."</a>
                                        </div>
                                    </div>
                                    <button id='survey-login-button' class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 checkable ".$survey_login_button_checked."' style='color:#865200;' onclick=\"showSurveyLoginSetupDialog();\">".RCIcon::OnlineDesignerSurveyLogin("me-1").RCView::tt("survey_573").RCIcon::CheckMarkCircle("button-checked")."</button> 
                                    <button id='survey-notifications-button' class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1 checkable ".(Survey::surveyNotificationsEnabled() ? "checked" : "")."' style='color:#000066;padding: 1px 8px 1px;' onclick=\"displayTrigNotifyPopup();\">".RCIcon::OnlineDesignerSurveyNotifications("me-1").RCView::tt("survey_548").RCIcon::CheckMarkCircle("button-checked")."</button>
                                    ".(!($twilio_enabled && $Proj->twilio_enabled_surveys) ? '' :
                "<button class='nowrap btn btn-defaultrc btn-xs fs11 ms-1 mb-1' onclick=\"dialogTwilioAnalyzeSurveys();\">".RCIcon::OnlineDesignerAnalyzeSMS("me-1").($Proj->messaging_provider == Messaging::PROVIDER_TWILIO ? $lang['survey_869'] : $lang['survey_1532'])."</button>"
            )."
                                </div>
                                ")."
                                
                                ".(!$mycap_access ? '' : "
                                <!-- MyCap Options -->
                                <div class='float-start wrap' style='max-width:560px;padding:8px 5px 0px 10px;color:#444;'>
                                    <div class='boldish mb-1'>".RCView::tt("mycap_mobile_app_451")."</div>
                                    <button class='nowrap btn btn-xs btn-defaultrc fs11 ms-1 mb-1' style='color:#00529f;border-color:#00529f;' onclick=\"window.location.href=app_path_webroot+'MyCapMobileApp/index.php?about=1&pid='+pid;\">".RCIcon::OnlineDesignerMobileDevice("me-1 fa-sm").RCView::tt("mycap_mobile_app_876")."</button>
                                    <button class='nowrap btn btn-xs btn-defaultrc fs11 ms-1 mb-1' onclick=\"displayTasksListing();\" ".($total == 0 ? "disabled" : "").">".RCIcon::OnlineDesignerViewDetails("me-1").RCView::tt("mycap_mobile_app_536")."</button>
                                    <button class='nowrap btn btn-xs btn-defaultrc fs11 ms-1 mb-1 checkable' onclick='displayMyCapAdditionalSettingsPopup();'>".RCView::fa(trim("fa-solid fa-gear"), "")." ".RCView::tt("econsent_148")."</button>
                                    ".((empty($taskErrors) && empty($taskNonFixableErrors)) ? '' :
                "<button class='nowrap btn btn-xs btn-defaultrc fs11 ms-1 mb-1' onclick=\"showMyCapIssues('');\">".RCIcon::ErrorNotificationTriangle("me-1 text-danger").RCView::tt("mycap_mobile_app_731")."</button>"
            )."
                                </div>
                                ")."
                            </div>
                        </div>";
    // append error messages for MyCap if instrument having any issues
    print $errorDivs;

    renderGrid("forms_surveys", $instTableTitle, $instTableWidth, 'auto', $col_widths_headers, $row_data, true, false);

    $baselineDateFieldForm = ZeroDateTask::getBaselineDateForm();

    $formListDownloadedFromLibrary = $Proj->getFormListDownloadFromLibrary();
    $formListDownloadedFromLibraryCSV = implode(",", $formListDownloadedFromLibrary);

    // Instrument action button/drop-down options (initially hidden)
    print 	RCView::div(array('id'=>'formActionDropdownDiv', 'style'=>'display:none;position:absolute;z-index:1000;'),
            RCView::ul(array('id'=>'formActionDropdown'),
                // Rename instrument
                (!($status == 0 || ($status > 0 && $draft_mode == '1')) ? '' :
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'line-height:14px;color:#006060;font-size:11px;', 'onclick'=>"renameForm($('#ActionCurrentForm').val());"),
                            RCView::img(array('src'=>'redo.png', 'style'=>'vertical-align:middle;')) .
                            RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_241'])
                        )
                    )
                ) .
                // Copy instrument
                (!($status == 0 || ($status > 0 && $draft_mode == '1')) ? '' :
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'line-height:14px;font-size:11px;', 'onclick'=>"if($('#ActionCurrentFormNumFields').val()=='0'){simpleDialog('".RCView::tt_js('design_1046')."');}else{ copyForm($('#ActionCurrentForm').val()); }"),
                            RCView::img(array('src'=>'copy_small.gif', 'style'=>'vertical-align:middle;')) .
                            RCView::span(array('style'=>'vertical-align:middle;'), $lang['report_builder_46'])
                        )
                    )
                ) .
                // Delete instrument
                (!($status == 0 || ($status > 0 && $draft_mode == '1')) ? '' :
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'line-height:14px;color:#800000;font-size:11px;', 'onclick'=>"deleteForm($('#ActionCurrentForm').val(), '".((is_array($baselineDateFieldForm)) ? json_encode($baselineDateFieldForm) : json_encode([$baselineDateFieldForm]))."');"),
                            RCView::img(array('src'=>'cross_small2.png', 'style'=>'vertical-align:middle;')) .
                            RCView::span(array('style'=>'vertical-align:middle;'), $lang['design_242'])
                        )
                    )
                ) .
                // Download instrument ZIP
                RCView::li(array(),
                    RCView::a(array('href'=>'javascript:;', 'style'=>'line-height:14px;color:#333;font-size:11px;', 'onclick'=>"downloadInstrumentZip($('#ActionCurrentForm').val(),false,'$formListDownloadedFromLibraryCSV');"),
                        RCView::img(array('src'=>'arrow_down_sm_orange.gif', 'style'=>'vertical-align:middle;')) .
                        RCView::span(array('style'=>'vertical-align:middle;color:#A86700;'), $lang['design_555'])
                    )
                ) .
                // Download instrument ZIP
                (!($status > 0 && $draft_mode == '1') ? '' :
                    RCView::li(array(),
                        RCView::a(array('href'=>'javascript:;', 'style'=>'line-height:14px;color:#333;font-size:11px;', 'onclick'=>"downloadInstrumentZip($('#ActionCurrentForm').val(),true,'$formListDownloadedFromLibraryCSV');"),
                            RCView::img(array('src'=>'arrow_down_sm_orange.gif', 'style'=>'vertical-align:middle;')) .
                            RCView::span(array('style'=>'vertical-align:middle;color:#A86700;'), $lang['design_555'] . " " . $lang['design_122'])
                        )
                    )
                )
            )
        ) .
        // Hidden input to temporarily store the current form selected when clicking the Choose Action drop-down
        RCView::hidden(array('id'=>'ActionCurrentForm', 'value'=>'')) .
        RCView::hidden(array('id'=>'ActionCurrentFormNumFields', 'value'=>''));

    // Invisible div used for Deleting a form dialog
    print 	RCView::div(array('id'=>'delete_form_dialog', 'class'=>'simpleDialog', 'title'=>$lang['design_44']),
        "{$lang['design_42']} \"<b id='del_dialog_form_name'></b>\" {$lang['design_43']}"
    );

    // Invisible div used for dialog for re-evaling ASIs
    print 	RCView::div(array('id'=>'reeval_asi_dlg', 'class'=>'simpleDialog', 'title'=>$lang['asi_023']), '');

    // Invisible div used for explaing what Instrument ZIP files are
    print 	RCView::div(array('id'=>'instrument_zip_explain_dialog', 'class'=>'simpleDialog', 'title'=>$lang['design_542']),
        $lang['design_543'] . " " .
        RCView::span(array('style'=>'color:#800000;'), $lang['design_553']) .
        RCView::div(array('style'=>'margin:10px 0;'),
            $lang['design_549'] . " " . RCView::b($lang['design_550'])
        ) .
        RCView::div(array('id'=>'external_instrument_list', 'loaded_list'=>'0', 'style'=>'padding:10px;background-color:#f5f5f5;border:1px solid #ddd;margin:15px 0 10px;'),
            RCView::img(array('src'=>'progress_circle.gif')) .
            RCView::span(array('style'=>'color:#666;margin-left:2px;'), $lang['design_544'])
        )
    );

    // AUTOMATED INVITATIONS: Hidden div containing list of events for user to choose from when setting up Automated Invitations (longitudinal or repeating instruments only)
    // Display hidden div
    print 	RCView::div(array('id'=>'choose_event_div'),
        RCView::div(array('id'=>'choose_event_div_sub'),
            RCView::div(array('style'=>'float:left;color:#B00000;width:260px;min-width:260px;font-weight:bold;font-size:13px;padding:6px 3px 5px;margin-bottom:3px;border-bottom:1px solid #ccc;'),
                $lang['survey_342'] .
                RCView::div(array('style'=>'padding:3px 0;color:#555;font-size:12px;font-weight:normal;'),
                    $lang['design_1025']
                )
            ) .
            RCView::div(array('style'=>'float:right;width:20px;padding:3px 0 0 3px;'),
                RCView::a(array('onclick'=>"$('#choose_event_div').fadeOut('fast');",'href'=>'javascript:;'),
                    RCView::img(array('src'=>'delete_box.gif'))
                )
            ) .
            RCView::div(array('class'=>'clear'), '') .
            RCView::div(array('id'=>'choose_event_div_loading','style'=>'padding:8px 3px;color:#555;'),
                RCView::img(array('src'=>'progress_circle.gif')) . RCView::SP .
                $lang['data_entry_64']
            ) .
            RCView::div(array('id'=>'choose_event_div_list','style'=>'padding:3px 6px;display:none;'), "")
        )
    );
    Survey::renderCheckComposeForSurveyLink();
}










// If production project is not in draft mode yet, prevent this page from being accessed
elseif (isset($_GET['page']) && $_GET['page'] != "" && $status > 0 && $draft_mode != '1')
{
    // Display nothing
}


/**
 * FORM WAS SELECTED - SHOW FIELDS
 */
elseif (isset($_GET['page']) && $_GET['page'] != "")
{
    // Enable an auto-appearing button to allow users to scroll to top of page
    outputButtonScrollToTop();

    // Instructions
    print "<p id='online-designer-instructions' style='margin:15px 0 0;max-width: 800px;'>";
    print RCView::lang_i("design_1215", [
        RCIcon::OnlineDesignerEdit(),
        RCIcon::OnlineDesignerDelete(),
        RCIcon::OnlineDesignerMove("drag-handle"),
    ], false);
    // print TakeATour::link("online-designer-fields", "ms-1"); // TODO: Reactivate once ready
    if ($status < 1) print RCView::tt("design_1216", "span", ["class" => "ms-1"]);
    print "</p>";

    // Show "previous page" link if editing a form
    print "<div class='clearfix' style='margin:20px 0 15px;padding-top:20px;max-width:800px;border-top:1px solid #ddd;'>";
    print "<div class='float-start'><button class='btn btn-xs btn-primaryrc fs13' onclick=\"window.location.href=app_path_webroot+page+'?pid='+pid;\"><i class='fas fa-chevron-circle-left'></i> {$lang['design_618']}</button></div>";

    // If coming from the Codebook, then give button to return
    if (isset($_GET['field']) || isset($_GET["r2cb"]))
    {
        $return_loc = isset($_GET['field']) ? "#field-".$_GET['field'] : "#form-".$_GET["page"];
        print "<div class='float-start' style='margin-left:10px;'><button class='btn btn-xs btn-defaultrc fs13' onclick=\"window.location.href=app_path_webroot+'Design/data_dictionary_codebook.php?pid='+pid+'$return_loc';\"><i class='fas fa-book fs12'></i> {$lang['design_617']}</button></div>";
    }

    // If instrument is enabled as a survey, then add button to go to Survey Settings page
    if ($surveys_enabled && isset($Proj->forms[$_GET['page']]['survey_id']))
    {
        print "<div class='float-start' style='margin-left:10px;'><button class='btn btn-xs btn-defaultrc fs13' onclick=\"window.location.href=app_path_webroot+'Surveys/edit_info.php?pid='+pid+'&view=showform&page={$_GET['page']}';\">".RCIcon::Survey("me-1").RCView::tt("survey_314")."</button></div>";
    }

    // If instrument is enabled for mycap, then add button to go to MyCap Settings page
    if ($mycap_enabled && $mycap_enabled_global && isset($myCapProj->tasks[$_GET['page']]['enabled_for_mycap']))
    {
        print "<div class='float-start' style='margin-left:10px;'><button class='btn btn-xs btn-defaultrc fs13' onclick=\"window.location.href=app_path_webroot+'MyCap/edit_task.php?pid='+pid+'&view=showform&page={$_GET['page']}';\">".RCIcon::MyCapTask("me-1").RCView::tt("mycap_mobile_app_314")."</button></div>";
    }

    // Display Prev/Next Instrument buttons
    $inDraftMode = ($Proj->project['status'] && $Proj->project['draft_mode']);
    $prevForm = $Proj->getPrevForm($_GET['page'], $inDraftMode);
    $nextForm = $Proj->getNextForm($_GET['page'], $inDraftMode);
    if ($nextForm) {
        print "<div class='float-end' style='margin-left:10px;'><button style='text-decoration:none;' class='btn btn-xs btn-link fs13' onclick=\"window.location.href=app_path_webroot+page+'?pid='+pid+'&page=$nextForm';\">{$lang['design_954']} <i class='fas fa-angle-double-right'></i></button></div>";
    }
    if ($prevForm) {
        print "<div class='float-end' style='margin-left:10px;'><button style='text-decoration:none;' class='btn btn-xs btn-link fs13' onclick=\"window.location.href=app_path_webroot+page+'?pid='+pid+'&page=$prevForm';\"><i class='fas fa-angle-double-left'></i> {$lang['design_953']}</button></div>";
    }

    print "</div>";

    ?>
    <!--#region Quick-modify field(s) popover -->
    <template data-template="qef-template">
        <div>
            <div data-part="title" class="title-row">
                <div class="panel-title">
                    <i class="fa-solid fa-wrench fs13 me-1"></i><?=RCView::tt("design_1142")?>
                </div>
                <div class="clear-selection ms-2">
                    <button type="button" class="btn btn-xs btn-default" data-multi-field-action="expand-toggle">
                        <?=RCIcon::OnlineDesignerModifySelection()?>
                    </button>
                    <button type="button" class="btn btn-xs btn-default" data-multi-field-action="clear" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_1134")?>">
                        <i class="fa-solid fa-times text-rc-danger"></i>
                    </button>
                </div>
            </div>
            <div data-part="content">
                <div class="multi-field-actions">
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_830")?>">
                        <button class="btn btn-sm btn-light" data-multi-field-action="copy">
                            <?=RCIcon::OnlineDesignerCopy()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_1143")?>">
                        <button class="btn btn-sm btn-light" data-multi-field-action="move">
                            <?=RCIcon::OnlineDesignerMove()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_826")?>">
                        <button class="btn btn-sm btn-light" data-multi-field-action="delete">
                            <?=RCIcon::OnlineDesignerDelete()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_1128")?>">
                        <button  class="btn btn-sm btn-light" data-multi-field-action="convert">
                            <?=RCIcon::OnlineDesignerConvertMatrix()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_1135")?>">
                        <button class="btn btn-sm btn-light" data-multi-field-action="branchinglogic">
                            <?=RCIcon::BranchingLogic()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_1136")?>">
                        <button class="btn btn-sm btn-light" data-multi-field-action="actiontags">
                            <?=RCIcon::ActionTags()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="top" title="<?=RCView::tt_attr("design_1263")?>">
                        <button class="btn btn-sm btn-light" data-multi-field-action="choices">
                            <?=RCIcon::OnlineDesignerEditChoices()?>
                        </button>
                    </div>
                    <hr>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1140")?>">
                        <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="required-ON">
                            <?=RCIcon::OnlineDesignerRequired()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1141")?>">
                        <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="required-OFF">
                            <?=RCIcon::OnlineDesignerRequiredOff()?>
                        </button>
                    </div>
                    <span class="text-rc-lightgray mx-1">|</span>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1138")?>">
                        <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="phi-ON">
                            <?=RCIcon::OnlineDesignerIdentifier()?>
                        </button>
                    </div>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1139")?>">
                        <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="phi-OFF">
                            <?=RCIcon::OnlineDesignerIdentifierOff()?>
                        </button>
                    </div>
                    <span class="text-rc-lightgray mx-1">|</span>
                    <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1262")?>">
                        <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="align-show">
                            <?=RCIcon::OnlineDesignerCustomAlignment()?>
                        </button>
                    </div>
                    <!-- COMING SOON
					<div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1264")?>">
						<button class="btn btn-xs btn-light grayish-action" data-multi-field-action="validation">
							<?=RCIcon::OnlineDesignerTextBoxValidation()?>
						</button>
					</div>
					<span class="text-rc-lightgray mx-1">|</span>
					<div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1265")?>">
						<button class="btn btn-xs btn-light grayish-action" data-multi-field-action="sliders">
							<?=RCIcon::OnlineDesignerEditSlider()?>
						</button>
					</div>
					<div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_1266")?>">
						<button class="btn btn-xs btn-light grayish-action" data-multi-field-action="notes">
							<?=RCIcon::OnlineDesignerEditFieldNote()?>
						</button>
					</div>
					-->
                    <!-- Footer row -->
                    <footer class="mt-2">
                        <?=RCView::lang_i("design_1137", [
                            '<span data-qef-content="current-index">0</span>',
                            '<span data-qef-content="selected-count">0</span>'
                        ], false, "small")
                        ?>
                        <span class="float-right">
							<button type="button" class="btn btn-xs btn-link" data-multi-field-action="navigate-prev" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1260")?>">
								<i class="fa-solid fa-chevron-up"></i>
							</button>
							<button type="button" class="btn btn-xs btn-link" data-multi-field-action="navigate-next" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1261")?>">
								<i class="fa-solid fa-chevron-down"></i>
							</button>
						</span>
                    </footer>
                    <!-- Warning -->
                    <div style="font-size:11px;color:#aaa;line-height:1.0;margin:1em 0.2em 0.4em 1.4em;text-indent:-0.65em;">
                        <?=RCIcon::Hint("me-1") . RCView::tt("design_1348")?>
                    </div>
                </div>
            </div>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Alignment popover -->
    <template data-template="qef-alignment">
        <div data-part="content">
            <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_213")?> (RV)">
                <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="align-RV">RV</button>
            </div>
            <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_214")?> (RH)">
                <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="align-RH">RH</button>
            </div>
            <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_215")?> (LV)">
                <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="align-LV">LV</button>
            </div>
            <div class="multi-field-action" data-bs-toggle="tooltip" data-bs-placement="bottom" title="<?=RCView::tt_attr("design_216")?> (LH)">
                <button class="btn btn-xs btn-light grayish-action" data-multi-field-action="align-LH">LH</button>
            </div>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Expand Selection popover -->
    <template data-template="qef-expand">
        <div>
            <div data-part="title" class="title-row">
                <?=RCIcon::OnlineDesignerModifySelection("me-1")?>
                <?=RCView::tt("design_1180")?>
                <button type="button" class="btn btn-xs btn-default ms-auto" data-qees-action="help">
                    <i class="fa-regular fa-circle-question"></i>
                </button>
            </div>
            <div data-part="content">
                <div class="quick-edit-options-dialog">
                    <div class="mb-3 fs13" style="max-width:500px;line-height:1.1;">
                        <?=RCView::tt("design_1347")?>
                    </div>
                    <hr>
                    <div class="qees-flex-container qees-direction">
                        <div class="me-2">
                            <?=RCView::tt("design_1185")?>
                        </div>
                        <label for="qees-dir-all" title="<?=RCView::tt_attr("design_1181")?>">
                            <input type="radio" name="qees-dir" class="visually-hidden" id="qees-dir-all" value="all" checked>
                            <?=RCIcon::OnlineDesignerUpUpDownDownToLine()?>
                        </label>
                        <label for="qees-dir-top" title="<?=RCView::tt_attr("design_1182")?>">
                            <input type="radio" name="qees-dir" class="visually-hidden" id="qees-dir-top" value="top">
                            <?=RCIcon::OnlineDesignerUpUpToLine()?>
                        </label>
                        <label for="qees-dir-bottom" title="<?=RCView::tt_attr("design_1183")?>">
                            <input type="radio" name="qees-dir" class="visually-hidden" id="qees-dir-bottom" value="bottom">
                            <?=RCIcon::OnlineDesignerDownDownToLine()?>
                        </label>
                        <label class="ms-3" for="qees-dir-first2last" title="<?=RCView::tt_attr("design_1184")?>">
                            <input type="radio" name="qees-dir" class="visually-hidden" id="qees-dir-first2last" value="first2last">
                            <?=RCIcon::OnlineDesignerUpDown()?>
                        </label>
                        <label class="ms-3" for="qees-dir-up" title="<?=RCView::tt_attr("design_1187")?>">
                            <input type="radio" name="qees-dir" class="visually-hidden" id="qees-dir-up" value="up">
                            <?=RCIcon::OnlineDesignerTurnUp()?>
                        </label>
                        <label for="qees-dir-down" title="<?=RCView::tt_attr("design_1188")?>">
                            <input type="radio" name="qees-dir" class="visually-hidden" id="qees-dir-down" value="down">
                            <?=RCIcon::OnlineDesignerTurnDown()?>
                        </label>
                    </div>
                    <hr>
                    <!-- #region Inclusions / Exclusions -->
                    <?php
                    $qees_inex_items = [
                        "radio" => [
                            "icon" => RCIcon::OnlineDesignerRadioField(),
                            "tootlip" => RCView::tt_attr("design_1190")
                        ],
                        "yesno" => [
                            "icon" => RCIcon::OnlineDesignerYesNoField(),
                            "tootlip" => RCView::tt_attr("design_1341")
                        ],
                        "truefalse" => [
                            "icon" => RCIcon::OnlineDesignerTrueFalseField(),
                            "tootlip" => RCView::tt_attr("design_1342")
                        ],
                        "select" => [
                            "icon" => RCIcon::OnlineDesignerDropdownField(),
                            "tootlip" => RCView::tt_attr("design_1194")
                        ],
                        "checkbox" => [
                            "icon" => RCIcon::OnlineDesignerCheckboxField(),
                            "tootlip" => RCView::tt_attr("design_1191")
                        ],
                        "text" => [
                            "icon" => RCIcon::OnlineDesignerTextBoxField(),
                            "tootlip" => RCView::tt_attr("design_1201")
                        ],
                        "number" => [
                            "icon" => RCIcon::OnlineDesignerTextBoxField_Numeric(),
                            "tootlip" => RCView::tt_attr("design_1206")
                        ],
                        "datetime" => [
                            "icon" => RCIcon::OnlineDesignerTextBoxField_Date(),
                            "tootlip" => RCView::tt_attr("design_1198")
                        ],
                        "email" => [
                            "icon" => RCIcon::OnlineDesignerTextBoxField_Email(),
                            "tootlip" => RCView::tt_attr("design_1192")
                        ],
                        "phone" => [
                            "icon" => RCIcon::OnlineDesignerTextBoxField_Phone(),
                            "tootlip" => RCView::tt_attr("design_1205")
                        ],
                        "textarea" => [
                            "icon" => RCIcon::OnlineDesignerNotesBoxField(),
                            "tootlip" => RCView::tt_attr("design_1195")
                        ],
                        "signature" => [
                            "icon" => RCIcon::OnlineDesignerSignatureField(),
                            "tootlip" => RCView::tt_attr("design_1200")
                        ],
                        "slider" => [
                            "icon" => RCIcon::OnlineDesignerSliderField(),
                            "tootlip" => RCView::tt_attr("design_1203")
                        ],
                        "file" => [
                            "icon" => RCIcon::OnlineDesignerFileUploadField(),
                            "tootlip" => RCView::tt_attr("design_1196")
                        ],
                        "descriptive" => [
                            "icon" => RCIcon::OnlineDesignerDescriptiveField(),
                            "tootlip" => RCView::tt_attr("design_1193")
                        ],
                        "calc" => [
                            "icon" => RCIcon::OnlineDesignerCalcField(),
                            "tootlip" => RCView::tt_attr("design_1197")
                        ],
                        "sql" => [
                            "icon" => RCIcon::OnlineDesignerSqlField(),
                            "tootlip" => RCView::tt_attr("design_1199")
                        ],
                        "matrix" => [
                            "icon" => RCIcon::OnlineDesignerConvertMatrix(),
                            "tootlip" => RCView::tt_attr("design_1202")
                        ],
                        "required" => [
                            "icon" => RCIcon::OnlineDesignerRequired(),
                            "tootlip" => RCView::tt_attr("design_1211")
                        ],
                        "phi" => [
                            "icon" => RCIcon::OnlineDesignerIdentifier(),
                            "tootlip" => RCView::tt_attr("design_1212")
                        ],
                    ];
                    ?>
                    <div class="qees-flex-container qees-include">
                        <button type="button" class="btn btn-xs btn-light" id="qees-incl-reset"
                                title="<?=RCView::tt_attr("design_1204")?>" data-qees-action="clear-include">
                            <i class="fa-solid fa-square-plus text-rc-green"></i> :
                        </button>
                        <?php foreach ($qees_inex_items as $val => $item): ?>
                            <label for="qees-incl-<?=$val?>" title="<?=$item["tootlip"]?>">
                                <input type="radio" name="qees-<?=$val?>" class="visually-hidden incl-excl" id="qees-incl-<?=$val?>" value="<?=$val?>">
                                <?=$item["icon"]?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <div class="qees-flex-container qees-exclude">
                        <button type="button" class="btn btn-xs btn-light" id="qees-excl-reset"
                                title="<?=RCView::tt_attr("design_1189")?>" data-qees-action="clear-exclude">
                            <i class="fa-solid fa-square-minus text-rc-red"></i> :
                        </button>
                        <?php foreach ($qees_inex_items as $val => $item): ?>
                            <label for="qees-excl-<?=$val?>" title="<?=$item["tootlip"]?>">
                                <input type="radio" name="qees-<?=$val?>" class="visually-hidden incl-excl" id="qees-excl-<?=$val?>" value="<?=$val?>">
                                <?=$item["icon"]?>
                            </label>
                        <?php endforeach; ?>
                    </div>
                    <!-- #endregion -->
                    <hr>
                    <div class="qees-flex-container mt-3 mb-2">
                        <button type="button" class="btn btn-xs fs13 btn-rcgreen btn-rcgreen-light ms-auto" id="qees-apply-expand" data-qees-action="apply-expand" data-bs-placement="bottom" title="<?=RCView::tt_js("design_1213")?>">
                            <?=RCIcon::OnlineDesignerExpand("fa-xs text-white")?>
                            <?=RCView::tt("design_1207") // Expand ?>
                        </button>
                        <?=RCView::tt("global_47",'span',['class'=>'text-secondary mx-2']) // Or ?>
                        <button type="button" class="btn btn-xs fs13 btn-rcred btn-rcred-light ms-1" id="qees-apply-reset" data-qees-action="apply-replace" data-bs-placement="bottom" title="<?=RCView::tt_js("design_1214")?>">
                            <?=RCIcon::OnlineDesignerReplace("fa-xs text-white")?>
                            <?=RCView::tt("design_1208") // Replace ?>
                        </button>
                    </div>
                </div>
            </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Branching Logic dialog -->
    <template data-template="qef-branchinglogic">
        <div class="quick-edit-options-dialog qef-apply">
            <div class="qebl-intro">
                <?=RCView::tt("design_1167")?>
                <div class="ms-2 mt-1"><i data-qebl-content="fields">Fields</i></div>
            </div>
            <div class="ms-1 mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qebl-mode" id="qebl-mode-clear" value="clear">
                    <label class="form-check-label" for="qebl-mode-clear">
                        <i class="fa-solid fa-eraser text-rc-danger"></i>
                        <?=RCView::tt("design_1169")?>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qebl-mode" id="qebl-mode-copy" value="copy">
                    <label class="form-check-label" for="qebl-mode-copy">
                        <i class="fa-solid fa-paint-roller text-rc-blue"></i>
                        <?=RCView::lang_i("design_1170", ["<i><b data-qebl-content='current'>Source</b></i>"], false)?>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qebl-mode" id="qebl-mode-new" value="new">
                    <label class="form-check-label" for="qebl-mode-new">
                        <i class="fa-solid fa-code"></i>
                        <?=RCView::tt("design_1171")?>
                    </label>
                </div>
                <div class="ms-3">
                    <textarea id="qebl-custom" readonly class="form-control" data-qebl-content="custom" rows="2"></textarea>
                </div>
            </div>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Action Tags dialog -->
    <template data-template="qef-actiontags">
        <div class="quick-edit-options-dialog qef-apply">
            <div class="qeat-intro">
                <?=RCView::tt("design_1173")?>
                <div class="ms-2 mt-1"><i data-qeat-content="fields">Fields</i></div>
            </div>
            <div class="ms-1 mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qeat-mode" id="qeat-mode-clear" value="clear">
                    <label class="form-check-label" for="qeat-mode-clear">
                        <i class="fa-solid fa-eraser text-rc-danger"></i>
                        <?=RCView::tt("design_1174")?>
                    </label>
                </div>
                <div>
                    &ndash; <?=RCView::tt("global_46")?> &ndash;
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="radio" name="qeat-mode" id="qeat-mode-append" value="append">
                    <label class="form-check-label" for="qeat-mode-append">
                        <i class="fa-regular fa-pen-to-square"></i>
                        <?=RCView::tt("design_1176")?>
                        <span class="float-end">
							<button type="button" class="btn btn-xs btn-rcred btn-rcred-light me-1" data-qeat-action="add-actiontags" style="line-height: 14px;padding:1px 3px;font-size:11px;">@ Action Tags</button>
							<button type="button" class="btn btn-xs btn-light me-2" data-qeat-action="clear-actiontags" style="line-height: 14px;padding:1px 3px;font-size:11px;" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1259")?>"><i class="fa-solid fa-xmark text-rc-danger"></i></button>
						</span>
                    </label>
                </div>
                <div class="ms-3">
                    <textarea id="qeat-custom" readonly class="form-control" data-qeat-content="custom" rows="2"></textarea>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="radio" name="qeat-mode" id="qeat-mode-deactivate" value="deactivate">
                    <label class="form-check-label" for="qeat-mode-deactivate">
                        <i class="fa-solid fa-toggle-off text-rc-danger"></i>
                        <?=RCView::tt("design_1353")?>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qeat-mode" id="qeat-mode-reactivate" value="reactivate">
                    <label class="form-check-label" for="qeat-mode-reactivate">
                        <i class="fa-solid fa-toggle-on text-rc-green"></i>
                        <?=RCView::tt("design_1354")?>
                    </label>
                    <div class="small ms-2">
                        <?=RCView::tt("design_1350")?>
                    </div>
                </div>
                <div>
                    &ndash; <?=RCView::tt("global_46")?> &ndash;
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="radio" name="qeat-mode" id="qeat-mode-copy" value="copy">
                    <label class="form-check-label" for="qeat-mode-copy">
                        <i class="fa-solid fa-paint-roller text-rc-blue"></i>
                        <?=RCView::lang_i("design_1175", ["<span data-qeat-content='current'>Source</span>"], false)?>
                    </label>
                </div>
                <div>
                    &ndash; <?=RCView::tt("global_46")?> &ndash;
                </div>
                <div class="mt-2">
                    <a href="javascript:;" data-qeat-action="edit-current">
                        <?=RCIcon::OnlineDesignerEdit()?>
                        <?=RCView::lang_i("design_1179", ["<span data-qeat-content='current'>Source</span>"], false)?>
                    </a>
                </div>
            </div>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Edit Choices (QEEC) dialog -->
    <template data-template="qef-editchoices">
        <div class="quick-edit-options-dialog qef-apply">
            <div class="qeec-intro">
                <?=RCView::tt("design_1269")?>
                <div class="ms-2 my-1"><i data-qeec-content="fields">Fields</i></div>
                <?=RCView::tt("design_1320")?>
            </div>
            <div class="ms-1 mt-3">
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qeec-mode" id="qeec-mode-copy" value="copy">
                    <label class="form-check-label" for="qeec-mode-copy">
                        <i class="fa-solid fa-paint-roller text-rc-blue"></i>
                        <?=RCView::lang_i("design_1270", ["<span class='mx-1' data-qeec-content='current'>Source</span>"], false)?>
                    </label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="radio" name="qeec-mode" id="qeec-mode-append" value="append">
                    <label class="form-check-label" for="qeec-mode-append">
                        <i class="fa-regular fa-square-plus text-rc-green"></i>
                        <?=RCView::tt("design_1272")?>
                        <span class="float-end">
							<button type="button" class="btn btn-xs btn-light me-2" data-qeec-action="clear-custom" style="line-height: 14px;padding:1px 3px;font-size:11px;" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1259")?>"><i class="fa-solid fa-xmark text-rc-danger"></i></button>
						</span>
                    </label>
                </div>
                <div class="ms-3">
                    <textarea id="qeec-custom" readonly class="form-control" data-qeec-content="custom" rows="2"></textarea>
                </div>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="radio" name="qeec-mode" id="qeec-mode-convert" value="convert">
                    <label class="form-check-label" for="qeec-mode-convert">
                        <i class="fa-solid fa-shuffle text-rc-danger"></i>
                        <?=RCView::tt("design_1274")?>
                    </label>
                </div>
                <div class="ms-4">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="qeec-convert" id="qeec-convert-checkbox" value="checkbox">
                        <label class="form-check-label" for="qeec-convert-checkbox">
                            <?=RCIcon::OnlineDesignerCheckboxField()?>
                            <?=RCView::tt("design_67")?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="qeec-convert" id="qeec-convert-radio" value="radio">
                        <label class="form-check-label" for="qeec-convert-radio">
                            <?=RCIcon::OnlineDesignerRadioField()?>
                            <?=RCView::tt("design_65")?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="qeec-convert" id="qeec-convert-select" value="select">
                        <label class="form-check-label" for="qeec-convert-select">
                            <?=RCIcon::OnlineDesignerDropdownField()?>
                            <?=RCView::tt("design_66")?>
                        </label>
                    </div>
                    <div class="form-check ms-4">
                        <input class="form-check-input" type="checkbox" name="qeec-autocomplete" id="qeec-convert-select-autocomplete" value="autocomplete">
                        <label class="form-check-label" for="qeec-convert-select-autocomplete">
                            <i class="fa-solid fa-wand-sparkles"></i>
                            <?=RCView::tt("design_602")?>
                        </label>
                    </div>
                    <div><?=RCView::tt("design_1319")?></div>
                </div>
                <div class="mt-2">
                    &ndash; <?=RCView::tt("global_46")?> &ndash;
                </div>
                <div class="mt-2">
                    <a href="javascript:;" data-qeec-action="edit-current">
                        <?=RCIcon::OnlineDesignerEdit()?>
                        <?=RCView::lang_i("design_1273", ["<span class='mx-1' data-qeec-content='current'>Source</span>"], false)?>
                    </a>
                </div>
            </div>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Set Question Number -->
    <template data-template="qef-set-question-num">
        <div class="qef-set-question-num input-group input-group-sm">
            <input type="text" class="form-control" data-qeqn-content="question-num" maxlength="10">
            <button type="button" class="btn btn-rcgreen">
                <i class="fa-solid fa-check"></i>
            </button>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit KVPair Editor -->
    <template data-template="qef-kvpair-editor">
        <div class="qef-kvpair-editor">
            <div class="qef-kvpair-intro">
                <?=RCView::tt("design_1279")?>
            </div>
            <div class="qef-kvpair-toolbar btn-toolbar" role="toolbar">
                <div class="btn-group me-2" role="group">
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1284")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="copy">
                        <i class="fa-regular fa-copy"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1285")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="from-paste">
                        <i class="fa-regular fa-paste text-rc-blue"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1301")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="undo">
                        <i class="fa-solid fa-arrow-rotate-left"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1297")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="cleanup">
                        <i class="fa-solid fa-broom text-rc-purple"></i>
                    </button>
                </div>
                <div class="btn-group me-2" role="group">
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1286")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="marked-up">
                        <i class="fa-solid fa-arrow-up"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1287")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="marked-down">
                        <i class="fa-solid fa-arrow-down"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1288")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="marked-mark">
                        <i class="fa-regular fa-square-plus text-rc-green"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1289")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="marked-unmark">
                        <i class="fa-regular fa-square-minus text-rc-danger"></i>
                    </button>
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1290")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="marked-delete">
                        <?=RCIcon::OnlineDesignerDelete()?>
                    </button>
                </div>
                <div class="btn-group me-2" role="group">
                    <button type="button" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1294")?>" class="btn btn-sm btn-light toolbar-btn" data-qef-kvpair-action="from-existing">
                        <i class="fa-solid fa-right-from-bracket fa-flip-horizontal"></i>
                    </button>
                    <div class="btn-group" role="group" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("design_1291")?>">
                        <button type="button" data-bs-toggle="dropdown" class="btn btn-sm btn-light toolbar-btn dropdown-toggle">
                            <i class="fa-solid fa-file-csv"></i>
                        </button>
                        <ul class="dropdown-menu">
                            <li><button class="dropdown-item btn btn-link" data-qef-kvpair-action="exportCsv-comma"><?=RCView::tt("global_162")?></button></li>
                            <li><button class="dropdown-item btn btn-link" data-qef-kvpair-action="exportCsv-semicolon"><?=RCView::tt("global_164")?></button></li>
                            <li><button class="dropdown-item btn btn-link" data-qef-kvpair-action="exportCsv-tab"><?=RCView::tt("global_163")?></button></li>
                        </ul>
                    </div>
                </div>
            </div>
            <div data-qef-kvpair-content="spreadsheet"></div>
        </div>
    </template>
    <!--#endregion -->
    <!--#region Quick-edit Goto Field modal -->
    <div class="modal fade" id="qef-goto-field-modal" tabindex="-1" aria-labelledby="qef-goto-field-modal-title" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="qef-goto-field-modal-title">
                        <?=RCView::tt("design_1321")?>
                    </h5>
                    <div class="mx-2">
                        <select name="qef-goto-fields" id="qef-goto-fields" placeholder="<?=RCView::tt_attr("design_1322")?>"></select>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=RCView::tt_attr("calendar_popup_01")?>"></button>
                </div>
            </div>
        </div>
    </div>
    <!--#endregion -->
    <?php

    $baseline_date_field = ZeroDateTask::getBaselineDateField();

    // Render javascript putting all form names in an array to prevent users from creating form+"_complete" field name, which is illegal
    print  "<script type='text/javascript'>
			var allForms = new Array('" . implode("','", array_keys($Proj->forms)) . "');
			var baseline_date_field = '".js_escape($baseline_date_field)."';
			</script>";

    // Get descriptive form name of selected form
    $this_form_menu_description = filter_tags($ProjForms[$_GET['page']]['menu'] ?? "");
    if ($this_form_menu_description == "") $this_form_menu_description = "[{$lang['global_01']}{$lang['colon']} {$lang['design_52']}]";
    // Link to edit form display name and form name (in DEV and DRAFT forms only)

    $repeating_indicator = $Proj->isRepeatingFormAnyEvent($_GET['page']) 
            ? RCView::span([
                "class" => "ms-2 badge badge-success fs8 info-item",
                "style" => "margin-top:3px;vertical-align:text-top;",
                "data-bs-toggle" => "tooltip",
                "title" => RCView::tt_attr("design_1391") // Instrument repeats on at least one event
            ], RCIcon::RepeatingIndicator())
            : "";
    $editFormMenu = RCView::div([ "class" => "od-form-name"],
        RCView::tt("design_1369", "span", [ "class" => "me-2" ]) .
        RCView::a([ 
            "href" => "javascript:;", 
            "onclick" => "editFormName(editFormNameData);",
            "title" => RCView::tt_attr("design_1365"),
        ], 
            RCView::span([ "id" => "form-name" ], $_GET["page"])
        ) .
        RCIcon::OnlineDesignerEdit("fa-xs ms-1 edit-icon")
    );
    $editFormNameData = [
        "selector" => "#form-name",
        "formName" => $_GET["page"],
        "displayName" => $this_form_menu_description,
        "surveyTitle" => $Proj->getSurveyTitle($_GET['page']) ?? false,
        "taskTitle" => $myCapProj && isset($myCapProj->tasks[$_GET['page']]) 
            ? $myCapProj->tasks[$_GET['page']]['title'] ?? false : false,
        "canEditFormName" => $Proj->canEditFormName($_GET['page']),
        "gotoPage" => true,
    ];
    $editFormNameData = json_encode($editFormNameData);
    print RCView::script("var editFormNameData = " . $editFormNameData . ";");
    print RCView::script("initTooltips('.od-form-name', { placement: 'top', subSelector: '[title]' });", true);
    $designateForEvent = Design::getDesignateForEventLink(PROJECT_ID, $_GET['page']);
    $custom_css = Design::getFormCustomCSS(PROJECT_ID, $_GET['page'], true);
    $custom_css = htmlspecialchars(label_decode($custom_css), ENT_QUOTES);
    $custom_css_btn = RCView::button([
            "id" => "edit-custom-css",
            "href" => "javascript:;",
            "onclick" => "openCSSEditor($('#custom_css'), false, () => saveFormCustomCSS('{$_GET['page']}'));",
            "class" => "btn btn-xs btn-defaultrc",
        ], 
        RCIcon::Palette("me-1 text-primary") . 
        RCView::tt($custom_css == "" ? "design_1393" : "design_1394", "span", ["id"=>"edit-custom-css-label"])
    );
    $custom_css_help = RCView::help(RCView::tt("design_1395"), RCView::tt("design_1396") . 
        (isset($Proj->forms[$_GET["page"]]['survey_id']) ? (RCView::p([], RCView::tt("design_1410"))) : "")
    );
    print  "<div style='padding:20px 0 10px 0;max-width:800px;'>
            <table cellspacing=0 width=100%>
            <tr>
                <td valign='top' width='100%'>".
                    RCView::tt("design_54", "span", [
                        "style" => "color:#666;font-size:14px;",
                        "class" => "me-2"
                    ]) . 
                    RCView::span([ 
                        "id" => "form-menu-description",
                        "class" => "notranslate",
                        "style" => "color:#800000;font-size:16px;font-weight:bold;"
                    ], $this_form_menu_description) . "
                    $repeating_indicator
                    $designateForEvent
                    $editFormMenu
                </td>
                <td style='white-space:nowrap;'>
                    $custom_css_btn
                    $custom_css_help
                    <textarea class=\"hidden visually-hidden d-none\" type=\"text\" id=\"custom_css\">$custom_css</textarea>
                </td>
            </tr>";

    $myCapEnabled = $Proj->project['mycap_enabled'];
    if ($myCapEnabled) {
        if (isset($myCapProj->tasks[$_GET['page']])) {
            print '<input name="is_form_enabled_for_mycap" id="is_form_enabled_for_mycap" type="hidden" value="1">';
        }
        loadJS('MyCapProject.js');
        if (isset($myCapProj->tasks[$_GET['page']]) && $myCapProj->tasks[$_GET['page']]['is_active_task'] == true) {
            print  "<tr id='blcalc-warn'>
                        <td valign='top' colspan='2' class='yellow' style=''>
                            {$lang['mycap_mobile_app_184']}
                        </td>
                    </tr>";
        }

        if (isset($myCapProj->tasks[$_GET['page']])) {
            $taskNonFixableErrors = Task::getMyCapTaskNonFixableErrors($_GET['page']);
            $myCapNonFixableErrText = '';
            if (!empty($taskNonFixableErrors)) {
                foreach ($taskNonFixableErrors as $error) {
                    $myCapNonFixableErrText .= $error;
                }
            }
            if ($myCapNonFixableErrText != '') {
                print  "<tr id='blcalc-warn'>
                            <td colspan='2' valign='top' class='red'>
                                <i class='fa fa-circle-exclamation' style='color: red;'></i> <b>".$lang['global_109']."</b> {$myCapNonFixableErrText} 
                            </td>
                        </tr>";
            }

            $taskErrors = Task::getMyCapTaskErrors($_GET['page']);
            $data['count'] = count($taskErrors);
            $myCapErrText = '';
            if (!empty($taskErrors)) {
                $myCapErrText = '<p>'.$lang['mycap_mobile_app_589'].'</p>';
                $myCapErrText .= '<ul>';
                foreach ($taskErrors as $error) {
                    $myCapErrText .= '<li style="padding-top: 5px;">' . $error . '</li>';
                }
                $myCapErrText .= '</ul>';
            }
            if ($myCapErrText != '') {
                $fixButton = '<button class="btn btn-success btn-xs fs13 my-2" onclick="fixMyCapIssues(\''.$_GET['page'].'\'); return false;" style="margin-left: 20px;"><span>Fix Issues</span></button>';
                print  "<tr id='blcalc-warn'>
                            <td valign='top' class='yellow' style='border-right: 0px;'>
                                {$myCapErrText} 
                            </td>
                            <td valign='bottom' align='left' class='yellow' style='border-left: 0px;'>
                                {$fixButton}
                            </td>
                        </tr>";
            }
        }
    }

    print  "<tr id='blcalc-warn' style='display:none;'>
			<td valign='top' colspan='2' class='yellow'>
				".RCView::tt("design_246")."
			</td>
		</tr>
		</table>
		</div>";

    ?>
    <style type="text/css">
        .labelrc, .labelmatrix, .data, .data_matrix {
            border:0; background:#f3f3f3;
        }
        .data  { max-width:400px; width:340px; }
        .header{ border:0; }
        .popover { z-index:100;}
        .frmedit_tbl td { z-index:10;}
        #online-designer-hint-card { z-index:1;}
        .ui-menu.ui-autocomplete.ui-front { z-index:103;}
        .jump-to-container-highlight {
            outline: 3px #C00000 dashed;
            outline-offset: -2px;
        }
    </style>
    <?php
    loadJS('DataEntrySurveyCommon.js');

    // Render the table of fields
    print  "<div id='draggablecontainer_parent'>";
    include APP_PATH_DOCROOT . "Design/online_designer_render_fields.php";
    print  "</div>";

    // Get recod list options to display in branching logic popup and when testing calc field equations
    $recordListOptions = Records::getRecordsAsOptions(PROJECT_ID, 200);

    /**
     * ADD/EDIT MATRIX OF FIELDS POP-UP
     */
    // For single survey or survey+forms project, see if custom question numbering is enabled for this survey
    $matrixQuesNumHdr = "";
    $matrixQuesNumRow = "";
    if (($surveys_enabled) && isset($Proj->forms[$_GET['page']]['survey_id'])
        && !$Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['question_auto_numbering'])
    {
        $matrixQuesNumHdr = "<td valign='bottom' class='addFieldMatrixRowQuesNum'>
								{$lang['design_342']}
								<div style='color:#888;font-size:10px;font-weight:normal;font-family:tahoma;'>{$lang['survey_251']}</div>
							</td>";
        $matrixQuesNumRow = "<td class='addFieldMatrixRowQuesNum'>
								<input name='matrix-ques-num-row' type='text' class='x-form-text x-form-field field_quesnum_matrix' style='width:35px;' maxlength='10'>
							</td>";
    }
    // Iframe for catching post data when adding Matrix fields
    print  "<iframe id='addMatrixFrame' name='addMatrixFrame' src='".APP_PATH_WEBROOT."DataEntry/empty.php' style='width:0;height:0;border:0px solid #fff;'></iframe>";
    //
    $matrixSHnote = '';
    if (isset($Proj->forms[$_GET['page']]['survey_id']) && $Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['question_by_section']) {
        $matrixSHnote = RCView::span(array('style'=>'font-size:11px;margin-left:20px;font-weight:normal;color:#000066;'), $lang['design_455']);
    }
    // Show auto-variable naming feature?
    $auto_variable_naming_displayed = ($allow_auto_variable_naming == '1' || ($allow_auto_variable_naming == '2' && UserRights::isSuperUserNotImpersonator()));
    if ($auto_variable_naming && !$auto_variable_naming_displayed) $auto_variable_naming = false; // If enabled in the project, then the system-level setting can override it, regardless.
    $auto_variable_naming_checked = ($auto_variable_naming && $auto_variable_naming_displayed);
    // Hidden div for adding/editing Matrix fields dialog
    print  "<div id='addMatrixPopup' title='".js_escape($lang['design_307'])."' style='display:none;background-color:#f5f5f5;'>
				<div style='margin:10px 0 15px;'>
					{$lang['design_310']}
					<a href='javascript:;' style='text-decoration:underline;' onclick=\"showMatrixExamplePopup();\">{$lang['design_355']}</a> {$lang['global_47']}
					<a href='javascript:;' style='text-decoration:underline;' onclick=\"helpPopup('3','category_12_question_5_tab_3');\">{$lang['design_358']}</a>
				</div>
				<div style='background:#FFFFE0;border: 1px solid #d3d3d3;padding:5px 8px 8px; margin-top: 10px;'>
					<!-- Section Header -->
					<div class='addFieldMatrixRowHdr' style='margin-bottom:6px;'>{$lang['design_454']}{$matrixSHnote}</div>
					<textarea id='section_header_matrix' name='section_header_matrix' class='x-form-textarea x-form-field' style='height:50px;width:95%;position:relative;'></textarea>
					<div id='section_header_matrix-expand' class='expandLinkParent'>
						<a href='javascript:;' class='expandLink' style='margin-right: 35px;' onclick=\"growTextarea('section_header_matrix')\">{$lang['form_renderer_19']}</a>&nbsp;
					</div>
				</div>
				<div style='border: 1px solid #d3d3d3; background-color: #eee; padding:5px 8px 8px; margin-top: 10px;'>
					<!-- Headers -->
					<div>
						<div class='addFieldMatrixRowHdr' style='float:left;margin:0;'>
							{$lang['design_316']}
						</div>
						<div style='float:right;padding-right:2px;".($auto_variable_naming_displayed ? "" : "display:none;")."'>
							<span id='auto_variable_naming_matrix_saved' style='visibility:hidden;text-align:center;font-size:9px;color:red;font-weight:bold;'>{$lang['design_243']}</span>
							<input type='checkbox' id='auto_variable_naming_matrix' " . ($auto_variable_naming_checked ? "checked" : "") . ">
							<span style='line-height:11px;color:#800000;font-family:tahoma;font-size:10px;font-weight:normal;' class='opacity75'>{$lang['design_267']}</span>
						</div>
						<div class='clear'></div>
						<div style='color:#777;font-size:11px;font-weight:normal;'>{$lang['design_341']}</div>
						<table cellspacing=0 style='width:100%;table-layout:fixed;'>
							<tr>
								<td valign='bottom' class='addFieldMatrixRowDrag'>&nbsp;</td>
								<td valign='bottom'  class='addFieldMatrixRowLabel'>{$lang['global_40']}</td>
								<td valign='bottom'  class='addFieldMatrixRowVar'>
									{$lang['global_44']}
									<div style='color:#888;font-size:10px;line-height:10px;font-weight:normal;font-family:tahoma;'>{$lang['design_80']}</div>
								</td>
								$matrixQuesNumHdr
								<td valign='bottom' class='addFieldMatrixRowFieldReq nowrap'>{$lang['design_98']}</td>
								<td valign='bottom' class='addFieldMatrixRowFieldAnnotation nowrap'>
									{$lang['design_527']}<a href='javascript:;' class='help' style='font-size:10px;margin-left:3px;' onclick=\"simpleDialog(null,null,'fieldAnnotationExplainPopup',550);\">?</a>
								</td>
								<td valign='bottom' class='addFieldMatrixRowDel'></td>
							</tr>
						</table>
					</div>

					<!-- Row with Label/Variable inputs -->
					<table class='addFieldMatrixRowParent' cellspacing=0 style='width:100%;table-layout:fixed;'>
						<tr class='addFieldMatrixRow'>
							<td class='addFieldMatrixRowDrag dragHandle'></td>
							<td class='addFieldMatrixRowLabel'>
								<input name='addFieldMatrixRow-label' class='x-form-text x-form-field field_labelmatrix' autocomplete='new-password' onkeydown='if(event.keyCode==13) return false;'>
							</td>
							<td class='addFieldMatrixRowVar'>
								<input name='addFieldMatrixRow-varname_' class='x-form-text x-form-field field_name_matrix' autocomplete='new-password' maxlength='100' onkeydown='if(event.keyCode==13) return false;'>
							</td>
							$matrixQuesNumRow
							<td class='addFieldMatrixRowFieldReq'>
								<input name='addFieldMatrixRow-required' type='checkbox' class='field_req_matrix'>
							</td>
							<td class='addFieldMatrixRowFieldAnnotation'>
								<textarea name='addFieldMatrixRow-annotation' class='x-form-textarea x-form-field field_annotation_matrix' style='font-size:11px; line-height: 13px;height:22px;width:97%;' onclick=\"$(this).css('height','36px');\" onfocus=\"$(this).css('height','36px');\"></textarea>
							</td>
							<td class='addFieldMatrixRowDel'>
								<a href='javascript:;' style='text-decoration:underline;font-size:10px;font-family:tahoma;' onclick='delMatrixRow(this)'><img src='".APP_PATH_IMAGES."cross.png' style='vertical-align:middle;' title='Delete Field'></a>
							</td>
						</tr>
					</table>

					<div style='padding:5px 0 0 30px;'>
						<button id='addMoreMatrixFields' style='font-size:11px;' onclick='return false;'>{$lang['design_314']}</button>
					</div>
				</div>
				<div>
					<!-- Choices --> 
					<div style='background-color: #eee; float:left;width:350px;border: 1px solid #d3d3d3; padding:5px 8px 8px; margin:10px 10px 0 0;'>
						<div class='addFieldMatrixRowHdr'>{$lang['design_317']}</div>
						<div style='font-weight:bold;'>
							{$lang['design_71']} <a href='javascript:;' style='font-weight:normal;margin-left:30px;font-size:11px;color:#3E72A8;text-decoration:underline;' onclick='existingChoices(1);'>{$lang['design_522']}</a>
						</div>
						<textarea class='x-form-textarea x-form-field' style='height:120px;width:100%;position:relative;' id='element_enum_matrix'
							name='element_enum_matrix'/></textarea>
						<div class='manualcode-label' style='padding-right:25px;'>
							<a href='javascript:;' style='color:#277ABE;font-size:11px;' onclick=\"
								$('#div_manual_code_matrix').toggle();
							\">{$lang['design_72']}</a>
						</div>
						<div id='div_manual_code_matrix' style='border:1px solid #ddd;font-size:11px;padding:5px 15px 5px 5px;display:none;'>
							{$lang['design_73']} {$lang['design_296']} ".RCView::lang_i('design_1092',['<br>'])."
							<div style='color:#800000;'>
								0, {$lang['design_311']}<br>
								1, {$lang['design_312']}<br>
								2, {$lang['design_313']}
							</div>
						</div>
					</div>
					<!-- Matrix Info -->
					<div style='background-color: #eee; float:left;font-weight:bold;border: 1px solid #d3d3d3; padding:5px 15px 8px 8px; margin-top: 10px;'>
						<div class='addFieldMatrixRowHdr''>{$lang['design_318']}</div>
						<!-- Answer Format -->
						<div>
							<div>{$lang['design_340']}</div>
							<select id='field_type_matrix' class='x-form-text x-form-field'
								style='' onchange='matrix_rank_disable();'>
								<option value='radio'>{$lang['design_319']}</option>
								<option value='checkbox'>{$lang['design_339']}</option>
							</select>
						</div>
						<!-- Ranking -->
						<div id='ranking_option_div' style='margin:15px 0 0;'>
							<div style='margin-left:5px;'>{$lang['design_495']}<a href='javascript:;' class='mtxrankDesc' style='margin-left:50px;'>{$lang['design_496']}</a></div>
							<table width=100%>
								<tr>
									<td><input type='checkbox' id='field_rank_matrix'></td>
									<td style='padding-left: 4px;'><span style='margin-right:5px;font-size:11px;font-weight:normal;'>{$lang['design_497']}</span></td>
								</tr>
							</table>
						</div>
						<!-- Matrix group name -->
						<div style='margin:15px 0 0;'>
							<div>{$lang['design_300']} <span style='margin-left:10px;color:#777;font-size:11px;font-weight:normal;'>{$lang['design_80']}</span></div>
							<input type='text' class='x-form-text x-form-field' style='width:160px;' maxlength='60' id='grid_name'>
							<a href='javascript:;' class='mtxgrpHelp'>{$lang['design_303']}</a>
						</div>
					</div>
					<!-- Hidden fields -->
					<input type='hidden' id='old_grid_name' value=''>
					<input type='hidden' id='old_matrix_field_names' value=''>
					<input type='hidden' id='split_matrix' value='0'>
					<div class='clear'></div>
				</div>
			</div>";

    /**
     * ADD/EDIT FIELD POP-UP
     */
    // Iframe for catching post data when adding/editing fields
    print  "<iframe id='addFieldFrame' name='addFieldFrame' src='".APP_PATH_WEBROOT."DataEntry/empty.php' style='width:0;height:0;border:0px solid #fff;'></iframe>";
    // Hidden div for adding/editing fields dialog
    print  "<div id='div_add_field' title='".js_escape($lang['design_57'])."' style='display:none;background-color:#f5f5f5;'>
			<div id='div_add_field2'>
				<form enctype='multipart/form-data' target='addFieldFrame' method='post' action='".APP_PATH_WEBROOT."Design/edit_field.php?pid=$project_id&page={$_GET['page']}' name='addFieldForm' id='addFieldForm'>
					<input type='hidden' id='wasSectionHeader' name='wasSectionHeader' value='0'>
					<input type='hidden' id='isSignatureField' name='isSignatureField' value='0'>
					<p style='max-width:100%;'>
						{$lang['design_58']}
						<i class=\"fas fa-film\"></i>
						<a onclick=\"popupvid('field_types03.mp4','REDCap Project Field Types');\" href=\"javascript:;\" style=\"font-size:13px;text-decoration:underline;font-weight:normal;\">{$lang['design_1361']}</a>.
					</p>
					<div id='add_field_settings' style='padding-top:5px;'>
						<div style='display:flex;justify-content:space-between;align-items: center;'>
							<div>
								<b class='fs14'>{$lang['design_61']}</b>&nbsp;
								<select name='field_type' id='field_type' onchange='selectQuesType()' class='x-form-text x-form-field fs14' style='max-width:100%;'>
									<option value=''> ---- {$lang['design_60']} ---- </option>
									<option value='text'>{$lang['design_634']}</option>
									<option value='textarea'>{$lang['design_63']}</option>
									<option value='calc'>{$lang['design_64']}</option>
									<option value='select'>{$lang['design_66']}</option>
									<option value='radio' grid='0'>{$lang['design_65']}</option>
									<option value='checkbox' grid='0'>{$lang['design_67']}</option>
									<option value='yesno'>{$lang['design_184']}</option>
									<option value='truefalse'>{$lang['design_185']}</option>
									<option value='file' sign='1'>{$lang['form_renderer_32']}</option>
									<option value='file' sign='0'>{$lang['design_68']}</option>
									<option value='slider'>{$lang['design_181']}</option>
									<option value='descriptive'>".($enable_field_attachment_video_url ? $lang['design_597'] : $lang['design_596'])."</option>
									<option value='section_header'>{$lang['design_69']}</option>
								</select>
							</div>
							<div>
								<span class='nowrap'><i class=\"fas fa-book fs12\" style='text-indent:0;'></i>&nbsp;<a href='".APP_PATH_WEBROOT."Design/data_dictionary_codebook.php?pid=$project_id' target='_blank' title='".RCView::tt_attr("design_1049")."'>".RCView::tt("design_482")."</a></span>
							</div>
						</div>
						<div id='quesTextDiv' style='visibility: hidden;' class='quesDivClass'>
							<table>
							<tr>
								<td valign='top' style='width: 65%;'>";
    // For single survey or survey+forms project, see if custom question numbering is enabled for this survey
    if (($surveys_enabled) && isset($Proj->forms[$_GET['page']]['survey_id'])
        && !$Proj->surveys[$Proj->forms[$_GET['page']]['survey_id']]['question_auto_numbering'])
    {
        // Render text box for question auto numbering
        print  "					<div id='div_question_num' style='padding-top:15px;'>
										<b>{$lang['design_221']}</b>
										<span style='color:#505050;font-size:11px;'>{$lang['global_06']}</span>&nbsp;
										<input type='text' class='x-form-text x-form-field' style='width:60px;' maxlength='10' id='question_num' name='question_num'>
										<div style='padding-left:2px;color:#808080;font-size:10px;font-family:tahoma;position:relative;top:-6px;'>
											{$lang['design_222']}
										</div>
									</div>";
    }
    print  "						<div style='padding-top:15px;'>
										<div style='font-weight:bold; margin-bottom: 8px; display: inline-block'>{$lang['global_40']}</div>
										<div style='float: right; margin-right: 18px'>
											<label style='margin-right:12px;color:#016301;'>
												<input id='field_label_rich_text_checkbox' type='checkbox' style='vertical-align:-2px' onchange='REDCap.toggleFieldLabelRichText()'>
												{$lang['design_783']}
												<a href='javascript:;' class='help' onclick=\"simpleDialog('".js_escape($lang['design_784'])."','<i class=\'fas fa-paragraph\'></i> ".js_escape($lang['design_783'])."',null,600);\">?</a>
											</label>
										</div>
										<div>
											<textarea class='x-form-textarea x-form-field mceEditor' style='height:200px;width:725px;resize:auto;' id='field_label' name='field_label'/></textarea>
											<script type='text/javascript'>
												REDCap.initTinyMCEFieldLabel(true); // Pre-init TinyMCE so it renders quickly later.
											</script>
										</div>
									</div>

									<div id='slider_labels' style='display:none;margin-top:20px;'>
										<div style='font-weight:bold;margin-bottom:3px;'>{$lang['design_668']}</div>
										<table style='width:100%;max-width:450px;'>
											<tr>
												<td>
													{$lang['design_665']}
												</td>
												<td>
													<input type='text' class='x-form-text x-form-field' style='margin:1px 0;width:120px;' maxlength='200' id='slider_label_left' name='slider_label_left' onkeydown='if(event.keyCode==13){return false;}'>
												</td>
											</tr>
											<tr>
												<td>
													{$lang['design_666']}
												</td>
												<td>
													<input type='text' class='x-form-text x-form-field' style='margin:1px 0;width:120px;' maxlength='200' id='slider_label_middle' name='slider_label_middle' onkeydown='if(event.keyCode==13){return false;}'>
												</td>
											</tr>
											<tr>
												<td>
													{$lang['design_667']}
												</td>
												<td>
													<input type='text' class='x-form-text x-form-field' style='margin:1px 0;width:120px;' maxlength='200' id='slider_label_right' name='slider_label_right' onkeydown='if(event.keyCode==13){return false;}'>
												</td>
											</tr>
											<tr>
												<td style='padding-top:6px;'>
													{$lang['design_941']}
												</td>
												<td style='padding-top:6px;'>
													<input type='checkbox' valign='middle' style='' id='slider_display_value' name='slider_display_value' onkeydown='if(event.keyCode==13){return false;}'>
												</td>
											</tr>
											<tr>
												<td style='padding-top:6px;'>
													{$lang['design_942']}
												</td>
												<td style='padding-top:6px;'>
												    <span class='me-2'>{$lang['design_486']} <input type='text' class='x-form-text x-form-field' style='width:50px;' maxlength='10' id='slider_min' name='slider_min' onkeydown='if(event.keyCode==13){return false;}' onblur=\"redcap_validate(this,'','','hard','integer',1);if(this.value==''){this.value='0';}\"></span>
												    <span>{$lang['design_487']} <input type='text' class='x-form-text x-form-field' style='width:50px;' maxlength='10' id='slider_max' name='slider_max' onkeydown='if(event.keyCode==13){return false;}' onblur=\"redcap_validate(this,'','','hard','integer',1);if(this.value==''){this.value='100';}\"></span>
												</td>
											</tr>
										</table>
									</div>

									<div id='div_pk_field_info' style='display:none;color:#C00000;font-size:11px;line-height:12px;padding:5px 20px 0 5px;'>
										<b>{$lang['global_02']}{$lang['colon']}</b> {$lang['design_434']}
									</div>

									<div id='div_element_yesno_enum' style='display:none;'>
										<div style='padding-top:15px;font-weight:bold;'>{$lang['design_512']}</div>
										<div style='padding: 2px 3px;margin-bottom: -2px;border: 1px solid #B5B8C8;background-color:#ddd;color:#555;height:60px;width:330px;position:relative;'>
											".str_replace(" \\n ", "<br>", YN_ENUM)."
										</div>
									</div>

									<div id='div_element_truefalse_enum' style='display:none;'>
										<div style='padding-top:15px;font-weight:bold;'>{$lang['design_512']}</div>
										<div style='padding: 2px 3px;margin-bottom: -2px;border: 1px solid #B5B8C8;background-color:#ddd;color:#555;height:60px;width:330px;position:relative;'>
											".str_replace(" \\n ", "<br>", TF_ENUM)."
										</div>
									</div>

									<div id='div_element_enum' style='display:none;'>
										<div style='padding-top:15px;font-weight:bold;'>
											<span id='choicebox-label-mc' style='display:none;'>
												{$lang['design_71']} 
												<button class='btn btn-xs btn-primaryrc btn-primaryrc-light' style='position:relative;top:-3px;margin-left:25px;font-size:11px;padding:0px 3px;'  onclick=\"openChoiceEditor();return false;\"><i class='fa-solid fa-list-ol mr-1'></i>{$lang['design_1349']}</button>
												<button class='btn btn-xs btn-defaultrc float-right' style='position:relative;top:-3px;margin-right:30px;font-size:11px;padding:0px 3px;'  onclick=\"existingChoices();return false;\"><i class='fa-solid fa-right-from-bracket fa-flip-horizontal mr-1'></i>{$lang['design_522']}</button>
											</span>
											<span id='choicebox-label-calc' style='display:none;'>
												{$lang['design_163']} &nbsp;&nbsp;
												<a href='javascript:;' onclick=\"helpPopup('3','category_15_question_7_tab_3');\" style='font-weight:normal;color:#277ABE;font-size:11px;'>{$lang['design_165']}</a>
												<span style='margin-left:25px;color:#808080;font-size:11px;font-weight:normal;'>
												{$lang['edit_project_186']}
												<button class='btn btn-xs btn-primaryrc btn-primaryrc-light' style='position:relative;top:-3px;margin-left:4px;font-size:11px;padding:0px 3px;'  onclick=\"specialFunctionsExplanation();return false;\"><i class='fas fa-square-root-alt' style='margin:0 2px 0 1px;'></i> {$lang['design_839']}</button>
												</span>
											</span>
											<span id='choicebox-label-sql' style='display:none;'>
												{$lang['design_164']}<button class='btn btn-primaryrc btn-xs' onclick='dialogSqlFieldExplain();return false;' style='margin:0 0 1px 20px;font-size:11px;padding:0 3px;'>{$lang['form_renderer_33']}</button>
												".($GLOBALS['database_query_tool_enabled'] == '1' ? "<button type='button' class='btn btn-primaryrc btn-xs' onclick='sqlFieldToDQT();' style='margin:0 0 1px 7px;font-size:11px;padding:0 3px;'>".RCView::tt("control_center_4803")."<i class='fs9 ms-1 fa-solid fa-arrow-up-right-from-square'></i></button>" : "")."
												<div class='text-secondary fs12 font-weight-normal mt-1 mb-2 ml-2 mr-5' style='line-height:1;'><i class=\"fa-solid fa-circle-info\"></i> ".RCView::lang_i('design_1102',[\Records::getDataTable(PROJECT_ID)])."</div>
											</span>
										</div>
										<div style='width: 725px;'><textarea hasrecordevent='0' class='x-form-textarea x-form-field' name='element_enum' id='element_enum' style='padding:1px;width:100%;height:120px;resize:auto;' onblur='logicHideSearchTip(this);' onfocus=\"if ($('#field_type').val() == 'calc' || $('#field_type').val() == 'sql') openLogicEditor($(this))\" onkeydown=\"if ($('#field_type').val() == 'calc') logicSuggestSearchTip(this, event, false, true, 0);\"></textarea>".logicAdd("element_enum")."</div>
										
										<div id='test-calc-parent' style='display:none;margin-top:20px;'>
											<table style='width:95%;'><tr>
											   <td style='border: 0; font-weight: bold; vertical-align: middle; text-align: left; height: 20px;'><span id='element_enum_Ok' class='logicValidatorOkay'></span></td>
											   <td style='vertical-align: top; text-align: right;'><a id='linkClearAdv' style='font-family:tahoma;font-size:10px;text-decoration:underline;' href='javascript:;' onclick='$(\"#element_enum\").val(\"\");logicValidate($(\"#element_enum\"), false);'>{$lang['design_711']}</a></td>
											</tr></table>
											<script type='text/javascript'>logicValidate($('#element_enum'), false, 0);</script>
											<div style='margin: 0 0 5px; '>
												<span class='logicTesterRecordDropdownLabel'>{$lang['design_704']}</span> 
												<select id='logicTesterRecordDropdown' onchange=\"
												var circle=app_path_images+'progress_circle.gif'; 
												if (this.value != '') { 
													$('#element_enum_res').html('<img src='+circle+'>'); 
												} else { 
													$('#element_enum_res').html(''); 
												} 
												logicCheck($('#element_enum'), 'calc', false, '', this.value, '".js_escape($lang['design_706'])."', '".js_escape($lang['design_707'])."', '".js_escape($lang['design_712'])."', 
													['', '', '".js_escape($lang['design_708'])."']);\">
												<option value=''>{$lang['data_entry_91']}</option>".$recordListOptions."</select><br>
												<span id='element_enum_res' style='color: green; font-weight: bold;'></span>
											</div>
										</div>
										<div style='margin-top:23px;'>
                                            <div id='div_autocomplete' style='display:none;font-weight:bold;margin:0 0 0 2px;'>
                                                <input type='checkbox' id='dropdown_autocomplete' name='dropdown_autocomplete'>
                                                {$lang['design_602']}<a href='javascript:;' class='help' onclick=\"simpleDialog('".js_escape($lang['design_603'])."','".js_escape($lang['design_604'])."');return false;\">?</a>
                                            </div>
                                            <div class='manualcode-label' style='text-align:right;padding-right:25px;'>
                                                <a href='javascript:;' style='color:#277ABE;font-size:11px;' onclick=\"
                                                    $('#div_manual_code').toggle();
                                                \">{$lang['design_72']}</a>
                                            </div>
										</div>
										<div id='div_manual_code' style='border:1px solid #ddd;font-size:11px;padding:5px 15px 5px 5px;display:none;'>
											{$lang['design_73']} {$lang['design_296']} ".RCView::lang_i('design_1092',['<br>'])."
											<div style='color:#800000;'>
												0, {$lang['design_74']}<br>
												1, {$lang['design_75']}<br>
												2, {$lang['design_76']}
											</div>
										</div>
									</div>
									<div id='div_field_annotation' style='width:525px;border: 1px solid #d3d3d3; padding: 6px 8px; margin-top: 20px;'>
										<div>
											<b>{$lang['global_132']}</b> /
											<b>{$lang['design_527']}</b> 
											<span style='color: #505050; font-size: 11px;'>{$lang['global_06']}</span>
										</div>
										<div id='div_parent_field_annotation' style='margin:0 0 1px;'>
											<textarea tabindex='-1' class='x-form-textarea x-form-field' style='width:99%;height:40px;font-size:13px;line-height:15px;background:#F7EBEB;' id='field_annotation' name='field_annotation' onfocus=\"openLogicEditor($(this), false, () => { showHideValidationForMCFields();});\"></textarea>
										</div>
										<div style='margin:5px 0;font-size:11px;color: #808080;'>
											{$lang['design_747']} 
											<button class='btn btn-xs btn-rcred btn-rcred-light' onclick=\"actionTagExplainPopup(0);return false;\" style='line-height: 14px;margin-left:3px;padding:0px 3px 1px;font-size:11px;'>@ {$lang['global_132']}</button>
											<span style='margin:0 1px;'>{$lang['global_47']}</span>
											<a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"simpleDialog(null,null,'fieldAnnotationExplainPopup',550);\">{$lang['design_673']}</a>
										    <div style='float: right' id='div_mc_slider_help'><i class='far fa-lightbulb'></i> <a href='javascript:;' style='text-decoration:underline;font-size:11px;' onclick=\"simpleDialog(null,null,'myCapSliderActionTagsExplainPopup',550);\">{$lang['mycap_mobile_app_695']}</a></div>
										</div>										
									</div>
								</td><td valign='top' style='width: 35%;'>
								<div id='baseline_date_warning' style='color:red;font-size:11px;display:none;'>{$lang['mycap_mobile_app_484']}</div>
										
									<div id='righthand_fields'>
                                        <div id='div_var_name' style='background-color: #ececec;border: 1px solid #d3d3d3; padding: 4px 4px 2px 8px; margin-top: 20px;'>
											<b>{$lang['global_44']}</b> 
											<span style='margin-left:7px;color: #777;font-size:11px;line-height:16px;'>{$lang['design_761']}</span><br/>
											<table cellspacing=0 width=100%>
												<tr>
													<td valign='top'>
														<input class='x-form-text x-form-field' autocomplete='new-password' maxlength='100' size='25'
															id='field_name' name='field_name'
															onkeydown='if(event.keyCode==13) return false;'
															onfocus='chkVarFldDisabled(this)'><br/>
														<div style='color: #888; font-size: 10px;margin-top:1px;'>{$lang['design_80']}</div>
													</td>
													<td valign='top' style='text-align:right;padding:2px 4px 0px 8px;".($auto_variable_naming_displayed ? "" : "display:none;")."'>
														<input type='checkbox' id='auto_variable_naming' " . ($auto_variable_naming_checked ? "checked" : "") . ">
														<div id='auto_variable_naming_saved' style='padding-top:2px;visibility:hidden;font-weight:bold;text-align:center;font-size:9px;color:red;'>{$lang['design_243']}</div>
													</td>
													<td valign='top' style='line-height:11px;padding:2px 0 0;color:#800000;font-family:tahoma;font-size:10px;".($auto_variable_naming_displayed ? "" : "display:none;")."' class='opacity75'>
														{$lang['design_267']}
													</td>
												</tr>
											</table>
										</div>
										
										<div style='padding:7px 4px 4px;'>
											<span style='color:#808080;font-size:11px;margin-right:6px;'>
												{$lang['design_748']}
											</span>
											<button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick=\"smartVariableExplainPopup();return false;\">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] {$lang['global_146']}</button>
											<button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='margin-right:6px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='".APP_PATH_IMAGES."pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'>{$lang['info_41']}</button>
											<button class='btn btn-xs btn-rcyellow' style='font-size:11px;padding:1px 3px;line-height:14px;'  onclick=\"fieldEmbeddingExplanation();return false;\"><i class='fas fa-arrows-alt' style='margin:0 1px;'></i> {$lang['design_795']}</button>						
					                    </div>

										<div id='div_val_type' style='border: 1px solid #d3d3d3; padding: 4px 8px; margin-top: 5px;'>
											<b>{$lang['design_81']}</b> <span style='color: #505050; font-size: 11px;'>{$lang['global_06']}</span>
											<select onchange=\"try { update_ontology_selection();hideOntologyServiceList(); }catch(e){ } hide_val_minmax();\" id='val_type' name='val_type' class='x-form-text x-form-field' style='width:235px;max-width:235px;margin-left:8px;'>
												<option value=''> ---- {$lang['design_83']} ---- </option>";
    // Get list of all valid field validation types from table
    $valTypesHidden = array();
    foreach (getValTypes() as $valType=>$valAttr)
    {
        if ($valAttr['visible']) {
            // Only display those listed as "visible"
            print "		<option value='$valType' datatype=\"".js_escape2($valAttr['data_type'])."\">{$valAttr['validation_label']}</option>";
        } else {
            // Add to list of hidden val types
            $valTypesHidden[] = $valType;
        }
    }
    print "									</select>
											<div id='div_val_minmax' style='padding:10px 0 2px 10px;display:none;'>
												<div class='mb-1'>
                                                    <b style='margin-right:8px;'>{$lang['design_96']}</b>
                                                    <input type='text' name='val_min' id='val_min' maxlength='200' size='18'
                                                        onkeydown='if(event.keyCode==13) return false;' class='x-form-text x-form-field' style='font-size:12px;'><br>
												</div>
												<div>
                                                    <b style='margin-right:6px;'>{$lang['design_97']}</b>
                                                    <input type='text' name='val_max' id='val_max' maxlength='200' size='18'
                                                        onkeydown='if(event.keyCode==13) return false;' class='x-form-text x-form-field' style='font-size:12px;'>
                                                </div>
												<div class='fs10' style='margin-top:14px;color:#808080;line-height:1;'>
												    <b><i class=\"far fa-lightbulb\"></i> {$lang['design_998']}</b> {$lang['design_1066']}
                                                </div>	
											</div>
											";
    if (OntologyManager::hasOntologyProviders()){print OntologyManager::buildOntologySelection();}
    print	"									</div>

										<div id='div_attachment' style='display:none;border: 1px solid #d3d3d3; padding: 4px 4px 4px 8px; margin-top: 5px;'>
											".(!$enable_field_attachment_video_url ? "" : "
											<div style='margin:1px 0 8px;color:#B00000;'>
												{$lang['design_1084']}
											</div>
											<div id='div_video_url'>
												<div style='margin-bottom: 2px;'>
													<i class=\"fa-solid fa-file-video fs14\" style='margin-right:1px;'></i>
													<b>{$lang['design_1085']}</b><a href='javascript:;' class='help' title='".js_escape($lang['form_renderer_02'])."' style='font-size:10px;' onclick=\"simpleDialog(null,null,'embed_video_explain',700);\">?</a>
												</div>
												<div style='margin:3px 0 0 22px;'>
													<span onclick=\"
														if ($('#video_url').prop('disabled')) {
															simpleDialog('".js_escape($lang['design_573'])."');
														}
													\"><input type='text' name='video_url' id='video_url' class='x-form-text x-form-field' placeholder='".js_escape($lang['design_1086'])."' style='width:95%;font-size:12px;' onkeydown='if(event.keyCode==13) return false;' onblur=\"
														this.value = trim(this.value);
														if (this.value.length == 0) return;
														// Validate URL as full or relative URL
														if (!isUrl(this.value) && this.value.substr(0,1) != '/' && this.value.indexOf('[') < 0) {
															if (this.value.substr(0,4).toLowerCase() != 'http' && isUrl('http://'+this.value)) {
																// Prepend 'http' to beginning
																this.value = 'http://'+this.value;
															} else {
																// Error msg
																simpleDialog('".js_escape($lang['edit_project_126'])."','".js_escape($lang['global_01'])."',null,null,'$(\'#video_url\').focus();');
															}
														}
													\"></span>
													<div style='margin-top:4px;text-indent:-2em;margin-left:2em;color:#888;font-size:11px;'>
														e.g. https://youtube.com/watch?v=E1cCuWMupz0, http://example.com/movie.mp4, [survey-url:instrument_name], https://redcap.myinstitution.org/surveys/?s=M9HL8L8WWT 
													</div>
													<div style='padding-top:8px;'>
														{$lang['design_1087']}&nbsp;
														<input disabled='disabled' id='video_display_inline1' name='video_display_inline' value='1' type='radio'> {$lang['design_580']}&nbsp;
														<input disabled='disabled' id='video_display_inline0' name='video_display_inline' value='0' checked='checked' type='radio'> {$lang['design_581']}
													</div>
												</div>
											</div>
											<div style='margin:10px 0 10px 6px;color:#777;'>
												&ndash; {$lang['global_47']} &ndash;
											</div>
											")."
											<div style='margin-bottom: 2px;'>
												<i class=\"fa-solid fa-paperclip fs14\" style='margin-right:1px;'></i> <b>{$lang['design_577']}</b>
											</div>
											<div style='margin:0 0 0 22px;'>
												<div id='div_attach_upload_link'>
													<img src='".APP_PATH_IMAGES."add.png'>
													<a href='javascript:;' onclick='openAttachPopup();' style='text-decoration:underline;color:green;'>{$lang['form_renderer_23']}</a>
												</div>
												<div id='div_attach_download_link' style='display:none;padding:3px 0;'>
													<a id='attach_download_link' href='javascript:;' onclick=\"window.open(app_path_webroot+'DataEntry/file_download.php?pid='+pid+'&type=attachment&id='+$('#edoc_id').val()+'&doc_id_hash='+$('#edoc_id_hash').val(),'_blank');\" style='text-decoration:underline;'>filename goes here.doc</a>
													&nbsp;&nbsp;
													<a href='javascript:;' class='nowrap' onclick='deleteAttachment();' style='color:#800000;font-family:tahoma;font-size:10px;'>[X] {$lang['data_entry_369']}</a>
												</div>
												<input type='hidden' id='edoc_id' name='edoc_id' value=''>
												<input type='hidden' id='edoc_id_hash' name='edoc_id_hash' value=''>
												<div id='div_img_display_options' style='padding-top:15px;'>
													{$lang['design_576']}<br>
													<input disabled='disabled' id='edoc_img_display_link' name='edoc_display_img' value='0' checked='checked' type='radio'> {$lang['design_196']}<br>
													<input disabled='disabled' id='edoc_img_display_image' name='edoc_display_img' value='1' type='radio'> {$lang['design_1053']}<br>
													<input disabled='disabled' id='edoc_img_display_audio' name='edoc_display_img' value='2' type='radio'> {$lang['global_122']}
													<div style='margin:1px 0 0 16px;'>
														<img src='".APP_PATH_IMAGES."information_small.png'><a href='javascript:;' 
															style='color:#3E72A8;font-size:11px;text-decoration:underline;' onclick=\"simpleDialog('".js_escape($lang['design_658'])."','".js_escape($lang['design_657'])."');\">{$lang['design_657']}</a>
													</div>
													<div style='font-family: tahoma; font-size: 10px; padding-top: 15px;'>
														{$lang['design_198']}
													</div>
												</div>
											</div>
										</div>

										<div id='div_field_req' style='border: 1px solid #d3d3d3; padding: 2px 8px; margin-top: 5px;'>
											<b>{$lang['design_98']}</b> &nbsp;
											<input type='radio' id='field_req0' name='field_req2'
												onclick=\"document.getElementById('field_req').value='0';\" checked>&nbsp;{$lang['design_99']}&nbsp;
											<input type='radio' id='field_req1' name='field_req2'
												onclick=\"document.getElementById('field_req').value='1';\">&nbsp;{$lang['design_100']}
											<input type='hidden' name='field_req' id='field_req' value='0'>
											<span id='req_disable_text' style='visibility:hidden;padding-left:10px;color:#800000;font-family:tahoma;'>
												{$lang['design_101']}
											</span>
											<div style='color:#808080;font-size:10px;font-family:tahoma;padding-top:2px;'>
												{$lang['design_102']}
											</div>
										</div>

										<div id='div_field_phi' style='color:#800000;border: 1px solid #d3d3d3; padding: 2px 8px 4px; margin-top: 5px;'>
											<b>{$lang['design_103']}</b> &nbsp;
											<input type='radio' id='field_phi0' name='field_phi2'
												onclick=\"document.getElementById('field_phi').value='';\" checked>&nbsp;{$lang['design_99']}&nbsp;
											<input type='radio' id='field_phi1' name='field_phi2'
												onclick=\"document.getElementById('field_phi').value='1';\">&nbsp;{$lang['design_100']}
											<input type='hidden' name='field_phi' id='field_phi' value=''>
											<div style='color:#808080;font-size:10px;font-family:tahoma;padding-top:2px;'>
												{$lang['design_166']}
											</div>
										</div>

										<div id='div_custom_alignment' style='border: 1px solid #d3d3d3; padding: 4px 8px; margin-top: 5px;'>
											<b>{$lang['design_212']}</b> &nbsp;
											<select id='custom_alignment' name='custom_alignment' class='x-form-text x-form-field' style=''>
												<option value=''>{$lang['design_213']} (RV)</option>
												<option value='RH'>{$lang['design_214']} (RH)</option>
												<option value='LV'>{$lang['design_215']} (LV)</option>
												<option value='LH'>{$lang['design_216']} (LH)</option>
											</select>
											<div style='color:#808080;font-size:10px;font-family:tahoma;padding-top:2px;'>
												{$lang['design_218']}
												<span id='customalign_disable_text' style='visibility:hidden;font-size:11px;padding-left:10px;color:#800000;font-family:tahoma;'>
													{$lang['design_101']}
												</span>
											</div>
											<div id='div_custom_alignment_slider_tip'>{$lang['design_669']}</div>
										</div>

										<div id='div_field_note' style='border: 1px solid #d3d3d3; padding: 4px 8px; margin-top: 5px;'>
											<b>{$lang['design_104']}</b> <span style='color: #505050; font-size: 11px;'>{$lang['global_06']}</span>
											<input class='x-form-text x-form-field' type='text' size='30' id='field_note' name='field_note'
												onkeydown='if(event.keyCode==13) return false;' style='width: 200px;margin-left: 5px;'>
											<div style='color:#808080;font-size:10px;font-family:tahoma;padding-top:2px;'>
												{$lang['design_217']}
											</div>
										</div>

										<!-- Hidden pop-up to note any non-numerical MC field fixes -->
										<div id='mc_code_change' style='display:none;padding:10px;' title='".js_escape($lang['design_294'])."'>
											{$lang['design_293']}
											<div id='element_enum_clone' style='padding:5px 8px;margin:15px 0 10px;width:90%;color:#444;border:1px solid #ccc;'></div>
											<div id='element_enum_dup_warning' style=''></div>
										</div>
										<input type='hidden' id='existing_enum' value=''>

									</div>
								</td>
							</tr>
							</table>
						</div>
					</div>
					<input type='hidden' name='form_name' value='{$_GET['page']}'>
					<input type='hidden' name='this_sq_id' id='this_sq_id' value=''>
					<input type='hidden' name='sq_id' id='sq_id' value=''>
				</form>
			</div>
			</div>
			<br><br>";
    ?>

    <!-- EXPLANATION DIALOG POP-UP FOR EMBEDDING VIDEOS -->
    <div id="embed_video_explain" title="<?php echo js_escape2($lang['design_1085']) ?>" class="simpleDialog">
        <div><?=$lang['design_1089']?></div>
        <div class="mt-3"><?=$lang['design_1090']?></div>
        <div class="mt-3 boldish"><?=$lang['design_1088']?></div>
        <div class="mt-3" style="color:#C00000;">
            <img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
            <?php print $lang['design_578'] ?>
        </div>
    </div>

    <!-- IMAGE/FILE ATTACHMENT DIALOG POP-UP -->
    <div id="attachment-popup" title="<?php echo js_escape2($lang['design_577']) ?>" class="simpleDialog">
        <!-- Upload form -->
        <form id="attachFieldUploadForm" target="upload_target" enctype="multipart/form-data" method="post"
              action="<?php echo APP_PATH_WEBROOT ?>Design/file_attachment_upload.php?pid=<?php echo $project_id ?>">
            <div style="font-size:13px;padding-bottom:5px;">
                <?php echo $lang['data_entry_62'] ?>
            </div>
            <input type="file" id="myfile" name="file" style="font-size:13px;">
            <div style="color:#555;font-size:13px;">(<?php echo $lang["data_entry_63"] . " " . maxUploadSizeAttachment() ?>MB)</div>
        </form>
        <iframe style="width:0;height:0;border:0px solid #ffffff;" src="<?php echo APP_PATH_WEBROOT ?>DataEntry/empty.php" name="upload_target" id="upload_target"></iframe>
        <!-- Response message: Success -->
        <div id="div_attach_doc_success" style="display:none;font-weight:bold;font-size:14px;text-align:center;color:green;">
            <img src="<?php echo APP_PATH_IMAGES ?>tick.png">
            <?php echo $lang['design_200'] ?>
        </div>
        <!-- Response message: Failure -->
        <div id="div_attach_doc_fail" style="display:none;font-weight:bold;font-size:14px;text-align:center;color:red;">
            <img src="<?php echo APP_PATH_IMAGES ?>exclamation.png">
            <?php echo $lang['design_137'] ?>
        </div>
        <!-- Upload in progress -->
        <div id="div_attach_doc_in_progress" style="display:none;font-weight:bold;font-size:14px;text-align:center;">
            <?php echo $lang['data_entry_65'] ?><br>
            <img src="<?php echo APP_PATH_IMAGES ?>loader.gif">
        </div>
    </div>

    <!-- DISABLE AUTO VARIABLE NAMING DIALOG POP-UP -->
    <div id="auto_variable_naming-popup" title="<?php echo js_escape2($lang['design_268']) ?>" class="round chklist" style="display:none;">
        <div class="yellow">
            <table cellspacing=5 width=100%><tr>
                    <td valign='top' style='padding:10px 20px 0 10px;'><img src="<?php echo APP_PATH_IMAGES ?>warning.png"></td>
                    <td valign='top'>
                        <p style="color:#800000;font-size:13px;font-family:verdana;"><b><?php echo $lang['design_268'] ?></b></p>
                        <p><?php echo $lang['design_269'] ?></p>
                        <p><?php echo $lang['design_270'] ?></p>
                        <p><?php echo $lang['design_271'] ?></p>
                    </td>
                </tr></table>
        </div>
    </div>

    <!-- STOP ACTIONS DIALOG POP-UP -->
    <div id="stop_action_popup" title="<?php echo js_escape2($lang['design_210']) ?>" style="display:none;"></div>

    <!-- LOGIC BUILDER DIALOG POP-UP -->
    <div id="logic_builder" title='<span style="color:var(--online-designer-branchinglogic-color);"><?=RCIcon::BranchingLogic("me-1").RCView::tt("design_225")?></span>' style="display:none;">
        <p style="line-height: 1.2em;font-size:12px;border-bottom:1px solid #ccc;padding-bottom:10px;margin:5px 0 0;">
            <?php echo $lang['design_226'] ?>
        </p>

        <div style="padding-top:10px;">
            <table cellspacing="0" width="100%">

                <tr>
                    <td valign="top" colspan="2" style="padding-bottom:4px;font-family:verdana;color:#777;font-weight:bold;">
                        <div style="width:700px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            <?php echo $lang['design_230'] ?>
                            <span id="logic_builder_field" style="color:#008000;padding-left:4px;"></span>
                            <span style="color:#008000;font-weight:normal;">- <i id="logic_builder_label"></i></span>
                        </div>
                    </td>
                </tr>

                <!-- Advanced Branching Logic text box -->
                <tr>
                    <td valign="top" style="padding:15px 20px 0 5px;">
                        <input checked type="radio" name="optionBranchType" onclick="chooseBranchType(this.value,true);" value="advanced">
                    </td>
                    <td valign="top">
                        <div style="font-weight:bold;padding:15px 20px 0 0;color:#800000;font-family:verdana;">
                            <?php echo $lang['design_231'] .
                                "<span style='font-weight:normal;color:#808080;font-size:11px;margin-right:4px;margin-left:40px;'>
											{$lang['design_748']}
										</span>
										<button class='btn btn-xs btn-defaultrc' style='color:#1049a0;margin-right:4px;font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick=\"helpPopup('3','category_16_question_1_tab_3');return false;\">".RCIcon::BranchingLogic("fa-xs me-1").RCView::tt("database_mods_74")."</button>
										<button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='margin-right:4px;font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick=\"smartVariableExplainPopup();return false;\">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] {$lang['global_146']}</button>
										<button class='btn btn-xs btn-primaryrc btn-primaryrc-light' style='font-size:11px;padding:1px 3px;line-height:14px;'  onclick=\"specialFunctionsExplanation();return false;\"><i class='fas fa-square-root-alt' style='margin:0 2px 0 1px;'></i> {$lang['design_839']}</button>
										";
                            ?>
                        </div>
                        <div id="logic_builder_advanced" class="chklist" style="border:1px solid #ccc;padding:8px 10px 2px;margin:5px 0 15px;max-width: 710px;">
                            <div style="padding-bottom:2px;">
                                <?php echo $lang['design_227'] ?>
                            </div>
                            <table style='width: 98%; border: 0;'>
                                <tr>
                                    <td colspan='2' style=' width: 100%; border: 0;'><textarea id="advBranchingBox" hasrecordevent="0" style="padding:1px;width:100%;height:65px;resize:auto;" onblur="logicHideSearchTip(this);" onkeydown="logicSuggestSearchTip(this, event, false, true, 0);" onfocus="openLogicEditor($(this))"></textarea><?php echo logicAdd("advBranchingBox"); ?></td>
                                </tr>
                                <tr>
                                    <td style='border: 0; font-weight: bold; text-align: left; vertical-align: middle; height: 20px;' id='advBranchingBox_Ok'>&nbsp;</td>
                                    <td style='border: 0; text-align: right; vertical-align: top;padding-right:10px;'><a id="linkClearAdv" style="font-family:tahoma;font-size:11px;text-decoration:underline;" href="javascript:;" onclick="$('#advBranchingBox').val('');logicValidate($('#advBranchingBox'), false);"><?php echo $lang['design_232'] ?></a></td>
                                </tr>
                            </table>
                            <script type='text/javascript'>logicValidate($('#advBranchingBox'), false, 0);</script>
                            <div style="margin: 0 0 4px;">
                                <span class='logicTesterRecordDropdownLabel'><?php echo $lang['design_705'] ?></span>
                                <?= Records::renderRecordListAutocompleteDropdown($Proj->project_id, false, 1000, "logicTesterRecordDropdown2", "fs11 x-form-text x-form-field", "", "", null, $lang['global_291'],
                                    'var circle=\''.APP_PATH_IMAGES.'progress_circle.gif\'; if (this.value !== \'\') $(\'#advBranchingBox_res\').html(\'<img src=\'+circle+\'>\'); else $(\'#advBranchingBox_res\').html(\'\'); logicCheck($(\'#advBranchingBox\'), \'branching\', '.($longitudinal ? 'true' : 'false').', \'\', this.value+'.'\'||'.$event_id.'\', \''.js_escape2($lang['design_706']).'\', \''.js_escape2($lang['design_707']).'\', \''.js_escape2($lang['design_713']).'\', [\''.js_escape2($lang['design_716']).'\', \''.js_escape2($lang['design_717']).'\', \''.js_escape2($lang['design_708']).'\'], \'advBranchingBox\');') ?>
                                <div id='advBranchingBox_res' style='margin-left:5px;color: green; font-weight: bold;'></div>
                            </div>
                        </div>

                    </td>
                </tr>

                <!-- OR -->
                <tr>
                    <td valign="top" colspan="2" style="padding:8px 15px 8px 0px;font-weight:bold;color:#777;">
                        &#8212; <?php echo $lang['global_46'] ?> &#8212;
                    </td>
                </tr>

                <!-- Drag-n-drop -->
                <tr>
                    <td valign="top" style="padding:15px 20px 0 5px;">
                        <input type="radio" name="optionBranchType" value="drag">
                    </td>
                    <td valign="top">
                        <div style="font-weight:bold;padding:15px 20px 0 0;font-family:verdana;color:#800000;"><?php echo $lang['design_233'] ?></div>
                        <div id="logic_builder_drag" class="chklist" style="height:270px;border:1px solid #ccc;padding:10px 10px 2px;margin:5px 0;">

                            <table cellspacing="0">
                                <tr>
                                    <td valign="bottom" style="width:290px;padding:20px 2px 2px;">
                                        <!-- Div containing options to drag over -->
                                        <b><?php echo $lang['design_234'] ?></b><br>
                                        <?php echo $lang['design_235'] ?><br>
                                        <div class="listBox" id="nameList" style="height:150px;overflow:auto;cursor:move;">
                                            <ul id="ulnameList"></ul>
                                        </div>
                                        <div style="font-size:11px;">&nbsp;</div>
                                    </td>
                                    <td valign="middle" style="text-align:center;font-weight:bold;font-size:11px;color:green;padding:0px 20px;">
                                        <img src="<?php echo APP_PATH_IMAGES ?>arrow_right.png"><br><br>
                                        <?php echo $lang['design_236'] ?><br>
                                        <?php echo $lang['global_43'] ?><br>
                                        <?php echo $lang['design_237'] ?><br><br>
                                        <img src="<?php echo APP_PATH_IMAGES ?>arrow_right.png">
                                    </td>
                                    <td valign="bottom" style="width:290px;padding:0px 2px 2px;">
                                        <!-- Div where options will be dragged to -->
                                        <b><?php echo $lang['design_227'] ?></b><br>
                                        <input type="radio" name="brOper" id="brOperAnd" value="and" onclick="updateAdvBranchingBox();" checked> <?php echo $lang['design_238'] ?><br>
                                        <input type="radio" name="brOper" id="brOperOr" value="or" onclick="updateAdvBranchingBox();"> <?php echo $lang['design_239'] ?><br>
                                        <div class="listBox" id="dropZone1" style="height:150px;overflow:auto;">
                                            <ul id="mylist" style="list-style:none;">
                                            </ul>
                                        </div>
                                        <div style="text-align:right;">
                                            <a id="linkClearDrag" style="font-family:tahoma;font-size:11px;text-decoration:underline;" href="javascript:;" onclick="
												$('#dropZone1').html('');
												updateAdvBranchingBox();
											"><?php echo $lang['design_232'] ?></a>
                                        </div>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </td>
                </tr>
            </table>
        </div>
    </div>

    <!-- BRANCHING LOGIC UPDATE DIALOG POP-UP -->
    <div id="branching_update" title="<?php echo isset($lang['design_999']) ? $lang['design_999'] : ''; ?></span>" class="simpleDialog">
        <?php echo isset($lang['design_1001']) ? $lang['design_1001'] : ''; ?>
        <br/><br/>
        <div>
            <em><input type="checkbox" id="branching_update_chk" name="branching_update_chk" value=""> <?php echo isset($lang['alerts_250']) ? $lang['alerts_250'] : ''; ?></em>
        </div>
    </div>

    <!-- CALCULATIONS HELP DIALOG POP-UP -->
    <div id="calc_help" title="<img src='<?php echo APP_PATH_IMAGES ?>help.png'> <span style='color:#3E72A8;'><?php echo $lang['help_10'] ?></span>" style="display:none;"></div>

    <!-- Tooltip when Choices textbox is pre-filled with matrix group name choices -->
    <div id="prefillChoicesTip" class="tooltip4" style="z-index:9999;"><?php echo $lang['design_305'] ?></div>

    <!-- MOVE FIELD DIALOG POP-UP -->
    <div id="move_field_popup" title="<?php echo js_escape2($lang['design_1242']) ?>" style="display:none;"></div>

    <!-- MOVE MATRIX DIALOG POP-UP -->
    <div id="move_matrix_popup" title="<?php echo js_escape2($lang['design_1243']) ?>" style="display:none;"></div>

    <!-- MATRIX EXAMPLES DIALOG POP-UP -->
    <div id="matrixExamplePopup" title="<?php echo js_escape2($lang['design_356']) ?>" style="display:none;"></div>

    <!-- FIELD ANNOTATION EXPLANATION DIALOG POP-UP -->
    <div id="fieldAnnotationExplainPopup" title="<?php echo js_escape2($lang['design_527']) ?>" class="simpleDialog"><?php echo $lang['design_529'] ?></div>

    <!-- MYCAP SLIDER ACTION TAG EXPLANATION DIALOG POP-UP -->
    <div id="myCapSliderActionTagsExplainPopup" title="<?php echo $lang['mycap_mobile_app_695'] ?>" class="simpleDialog"><?php echo $lang['mycap_mobile_app_694'] ?></div>

    <div id="online-designer-hint-card">
        <!-- FLOATING CARD OF COLORED BUTTONS -->
        <div class="card mb-4">
            <div class="card-body p-2">
                <h5 class="card-title fs14 boldish mb-1"><i class="far fa-lightbulb"></i> <?php echo $lang['design_955'] ?></h5>
                <div class="card-text clearfix"><?php
                    echo   "<div class='float-start ms-2 my-1'><button class='btn btn-xs btn-rcgreen btn-rcgreen-light' style='font-size:11px;padding:0px 3px 1px;line-height:14px;'  onclick=\"smartVariableExplainPopup();return false;\">[<i class='fas fa-bolt fa-xs' style='margin:0 1px;'></i>] {$lang['global_146']}</button></div>
                            <div class='float-start ms-2 my-1'><button class='btn btn-xs btn-rcpurple btn-rcpurple-light' style='font-size:11px;padding:0px 3px 1px;line-height: 14px;' onclick='pipingExplanation();return false;'><img src='".APP_PATH_IMAGES."pipe.png' style='width:12px;position:relative;top:-1px;margin-right:2px;'>{$lang['info_41']}</button></div>
                            <div class='float-start ms-2 my-1'><button class='btn btn-xs btn-rcred btn-rcred-light' onclick=\"actionTagExplainPopup(1);return false;\" style='line-height: 14px;padding:1px 3px;font-size:11px;'>@ {$lang['global_132']}</button></div>
                            <div class='float-start ms-2 my-1'><button class='btn btn-xs btn-rcyellow' style='font-size:11px;padding:1px 3px;line-height:14px;'  onclick=\"fieldEmbeddingExplanation();return false;\"><i class='fas fa-arrows-alt' style='margin:0 1px;'></i> {$lang['design_795']}</button></div>
                            <div class='float-start ms-2 my-1'><button class='btn btn-xs btn-primaryrc btn-primaryrc-light' style='font-size:11px;padding:1px 3px;line-height:14px;'  onclick=\"specialFunctionsExplanation();return false;\"><i class='fas fa-square-root-alt' style='margin:0 2px 0 1px;'></i> {$lang['design_839']}</button></div>";
                    ?></div>
            </div>
        </div>
        <!-- FLOATING REMINDER FOR HOW TO USE FIELD EMBEDDING -->
        <div class="card mb-4">
            <div class="card-body p-2">
                <h5 class="card-title fs14 boldish"><i class="far fa-lightbulb"></i> <?php echo $lang['design_794'] ?></h5>
                <p class="card-text fs12" style="line-height: 1.25;"><?php echo $lang['design_831'] ?> <a href="javascript:;" style="text-decoration:underline;" class="fs12" onclick="fieldEmbeddingExplanation();return false;"><?php echo $lang['design_795'] ?></a><?php echo $lang['period'] ?></p>
            </div>
        </div>
        <!-- FLOATING REMINDER FOR HOW TO USE MULTI FIELD SELECT OPTIONS -->
        <?php
        $qef_preferred_location = UIState::getUIStateValue("", "online-designer", "qef-preferred-location") ?? "right";
        ?>
        <div id="online-designer-qef-card" class="card mb-4">
            <div class="card-body p-2">
                <h5 class="card-title fs14 boldish"><i class="far fa-lightbulb"></i> <?php echo $lang['design_827'] ?></h5>
                <p class="card-text fs12" style="line-height: 1.25;"><?=RCView::tt("design_1217")?></p>
                <p class="qef-preferred-location">
                    <?=RCView::tt("design_1306")?>
                    <input type="radio" id="qef-preferred-location_top" name="qef-preferred-location" value="top" <?= $qef_preferred_location == "top" ? "checked" : ""?>>
                    <label for="qef-preferred-location_top"><?=RCview::tt("design_1307")?></label>
                    <input type="radio" id="qef-preferred-location_right" name="qef-preferred-location" value="right" <?= $qef_preferred_location == "right" ? "checked" : ""?>>
                    <label for="qef-preferred-location_right"><?=RCview::tt("design_1308")?></label>
                </p>
            </div>
        </div>
        <!-- FLOATING / STICKY NAVIGATION AIDS -->
        <div id="online-designer-fn-card" class="card mb-4">
            <div class="card-body p-2">
                <h5 class="card-title fs14 boldish"><?=RCIcon::OnlineDesignerFieldNavigator("me-1")?><?=RCView::tt("design_1357") // Field Navigator ?></h5>
                <p class="card-text fs12" style="line-height: 1.25;">
                    <?=RCView::lang_i("design_1324", [
                        '<a class="goto-link" data-multi-field-action="goto-show" href="javascript:;"><u>',
                        '</u></a>',
                        '<span class="badge badge-secondary shortcut">CTRL-G</span>',
                        '<span class="badge badge-secondary shortcut">CMD-G</span>',
                    ], false)?>
                </p>
                <div id="online-designer-fn-card-sh" class="hidden card-text fs12" style="line-height: 1.25;">
                    <b><?=RCView::tt("design_1358") // Scroll to Section Header: ?></b>
                    <ul id="online-designer-fn-card-sh-list"></ul>
                </div>
            </div>
        </div>
    </div>
    <script>
      const onlineDesignerQefCard = document.getElementById('online-designer-qef-card');
      const onlineDesignerFnCard = document.getElementById('online-designer-fn-card');
      window.addEventListener('scroll', () => {
        const qefRect = onlineDesignerQefCard.getBoundingClientRect();
        if (qefRect.bottom <= 0) {
          onlineDesignerFnCard.classList.add('online-designer-fn-card-sticky');
        } else {
          onlineDesignerFnCard.classList.remove('online-designer-fn-card-sticky');
        }
      });
    </script>

    <!-- Set variables and static msgs -->
    <script type="text/javascript">
      var form_name = '<?php echo $_GET['page'] ?>';
      var edit_mode = '<?php echo isset($_GET['edit_mode']) ? $_GET['edit_mode'] : ''; ?>';
      var valTypesHidden = new Array('<?php echo implode("', '", $valTypesHidden) ?>');
      var hide_pk = <?php echo (($surveys_enabled) && isset($_GET['page']) && $_GET['page'] == $Proj->firstForm) ? 'true' : 'false' ?>; // Hide first field for Single Survey projects only
      var isMyCapEnabled = '<?php print $mycap_enabled; ?>';
      // Put all reserved variable names into an array for checking later
      var reserved_field_names = new Array(<?php
          echo prep_implode(array_keys(\Project::$reserved_field_names))
              . ",'" . implode("_timestamp','", array_keys($Proj->forms)) . "_timestamp'"
              . ",'" . implode("_return_code','", array_keys($Proj->forms)) . "_return_code'"
          ?>);
    </script>
    <link rel="stylesheet" type="text/css" href="<?php echo APP_PATH_CSS ?>bootstrap-select.min.css">
    <?php
    loadJS("Libraries/jsuites.js");
    loadJS("Libraries/jspreadsheet.js");
    loadJS('Libraries/tablednd.js');
    loadJS('Libraries/jquery.simplePagination.js');
    loadJS('Libraries/bootstrap-select-min.js');
    loadJS('DesignFields.js');
    loadJS('FieldBank.js');
    ?>
    <!-- Field Bank dialog -->
    <div id="add_fieldbank">
        <div id="questionBankContainer">
            <div style="margin:5px 2px 10px 2px;">
                <?=$lang['design_907']; ?>
            </div>
            <div class="clear"></div>
            <div class="row">
                <div class="col">
                    <div class="input-group">
                        <span class="input-group-text fs13"><?=$lang['design_935']?></span>
                        <select id="classification-list" data-header="<?=js_escape2("<div style='color:#800000;font-size:14px;background-color:#eee;padding:5px;'>{$lang['design_931']}</div>")?>" data-style="btn-defaultrc" data-dropup-auto="false" data-size="8" class="show-menu-arrow form-control" data-style="btn-white"><?=FieldBank::getClassificationDropDown()?></select>
                    </div>
                    <div class="input-group" style="padding-top: 10px;">
                        <span id="basic-addon2" class="input-group-text" onclick="doFieldBankSearch();"><i class="fa fa-search"></i></span>
                        <input autocomplete="off" class="form-control py-2" type="search" placeholder="<?=js_escape2($lang['messaging_161'])?>" value="" id="keyword-search-input" aria-describedby="basic-addon2">
                    </div>
                </div>
            </div>
            <div class="clear"></div>
            <div id="fieldbank-result-container">
                <div id="cde_search_result"></div>
                <div class="clear"></div>
                <div id="fieldbank-pagination-container">
                    <nav>
                        <ul class="pagination"></ul>
                    </nav>
                </div>
            </div>
        </div>
    </div>
    <?php

    // If field name and type are passed in query string, then open Edit Field popup
    if (isset($_GET['field']) && isset($Proj->metadata[$_GET['field']]))
    {
        if (isset($_GET['branching'])) { ?>
            <script type="text/javascript">
              $(function(){ setTimeout(function(){
                openLogicBuilder('<?php echo $_GET['field']; ?>');
              },1000); });
            </script>
        <?php } elseif (isset($_GET['matrix'])) { ?>
            <script type="text/javascript">
              $(function(){ setTimeout(function(){
                openAddMatrix('<?php echo $_GET['field']; ?>', '');
              },1000); });
            </script>
        <?php } else { ?>
            <script type="text/javascript">
              $(function(){ setTimeout(function(){
                openAddQuesForm('<?php echo $_GET['field']; ?>', '<?php echo $Proj->metadata[$_GET['field']]['element_type']; ?>', 0, '<?php print (($Proj->metadata[$_GET['field']]['element_type'] == 'file' && $Proj->metadata[$_GET['field']]['element_validation_type'] == 'signature') ? '1' : '0') ?>');
              },1000); });
            </script>
        <?php }
    }
}

if (isset($_GET['page']) && !empty($_GET['page']) && UIState::getUIStateValue("", "online-designer", "dismissed_new_drag_and_drop_info") != "1")
{
    print RCView::script('
		const dndIcon = $("#online-designer-instructions .drag-handle")[0];
		const dndContent = $("<div><p>" + lang.design_1351 + "</p></div>");
		const button = $("<button>" + lang.design_1352 + "</button>")
			.addClass("btn btn-primary btn-xs")
			.appendTo(dndContent);
			
		const popover = new bootstrap.Popover(dndIcon, {
			html: true,
			content: dndContent,
			placement: "bottom"
		});
		popover.show();
		button.on("click", function() {
			popover.dispose();
			dismissNewDragAndDropInfo();
		});
	', true);
}
?>
<!--#region Quick-edit Edit Form Name popover -->
<template data-template="qef-editformname">
    <div>
        <header>
            <?=RCIcon::OnlineDesignerEdit("me-1")?>
            <?=RCView::tt("design_1365")?>
        </header>
        <main>
            <div class="mb-1">
                <label for="efn-displayname"><?=RCView::getLangStringByKey("design_244")?></label>
                <input type="text" class="form-control form-control-sm" id="efn-displayname" minlength="1" maxlength="200">
                <div id="efn-displayname-error" class="invalid-feedback"></div>
            </div>
            <div class="form-check" id="efn-surveytitle">
                <input class="form-check-input" type="checkbox" value="" id="efn-change-surveytitle">
                <label class="form-check-label" for="efn-change-surveytitle">Placeholder</label>
            </div>
            <div class="form-check mb-1" id="efn-tasktitle">
                <input class="form-check-input" type="checkbox" value="" id="efn-change-tasktitle">
                <label class="form-check-label" for="efn-change-tasktitle">Placeholder</label>
            </div>
            <div class="mb-2">
                <label for="efn-formname"><?=RCView::getLangStringByKey("design_1368")?> *</label>
                <input type="text" class="form-control form-control-sm" id="efn-formname" minlength="1" maxlength="50">
                <div id="efn-formname-error" class="invalid-feedback"></div>
            </div>
            <div class="hint can-edit">
                * <?=RCView::tt("design_1370")?>
            </div>
            <div class="hint cannot-edit">
                * <?=RCView::tt("design_1376")?>
            </div>
        </main>
        <footer>
            <button type="button" data-action="apply" class="btn btn-xs btn-defaultrc fw-bold"><?=RCView::tt("design_1168")?></button>
            <button type="button" data-action="cancel" class="btn btn-xs btn-defaultrc"><?=RCView::tt("global_53")?></button>
        </footer>
    </div>
</template>
<!--#endregion -->
<?php

include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';