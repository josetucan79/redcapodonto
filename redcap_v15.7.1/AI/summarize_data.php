<?php

use Vanderbilt\REDCap\Classes\OpenAI\ChatGPTSummary;
use Vanderbilt\REDCap\Classes\OpenAI\Prompts;
use Vanderbilt\REDCap\Classes\GeminiAI\GeminiSummary;

require_once dirname(dirname(__FILE__)) . '/Config/init_project.php';

if (isset($_POST['action']) && $_POST['action'] == 'get_html') {
    global $Proj;
    $report_id = $_POST['report_id'];
    $report_name = DataExport::getReportNames($report_id, false, true, true, PROJECT_ID);
    $dialog_title = RCView::span(['style'=>'color:#d31d90;'], RCView::fa('fa-solid fa-wand-sparkles') . " " . RCView::tt('openai_101',''))
        . RCView::span(['class'=>'text-secondary font-weight-normal'], RCView::span(['class'=>'mx-2'], "&ndash;") . RCView::tt('report_builder_212','')) . " \"$report_name\"";

    $summary_details = AI::getReportAISummaryDetails($report_id);
    $fields = $summary_details['summary_fields'];

    $cells = "";
    foreach ($fields as $field) {
        $field_label = strip_tags($Proj->metadata[$field]['element_label']);
        $rows .= RCView::tr(array('class' => 'summary-rows'),
            RCView::td(array('class'=>'wrap', 'style' => 'width: 30%;vertical-align:top;background-color:#f5f5f5;padding:11px 7px;font-weight:bold;border:1px solid #ccc;border-bottom:0;'),
                $field_label.RCView::br().RCView::span(array('class'=>'fs11 mt-1 d-print-none summary-field', 'style'=>'margin-top:10px; color:#6f6f6f; font-weight: normal;'), $field)
            ) .
            RCView::td(array('style'=>'line-height:1.3;font-size:13px;background-color:#f5f5f5;padding:9px 7px;border:1px solid #ccc;border-bottom:0;border-left:0;position:relative;', 'class'=>'summary-result'),
                RCView::button(array('class'=>'btn btn-xs fs13 btn-defaultrc', 'style'=>'color:#d31d90;', 'onclick'=>'generateSummary(this, "'.$report_id.'", "'.$field.'");'), RCView::fa('fa-solid fa-wand-sparkles mr-1').RCView::tt('openai_106'))
            )
        );
    }

    $rows = RCView::tr(array(),
            RCView::th(array('class' => 'header', 'style' => 'color:#800000; line-height:20px;'),
                RCView::tt('openai_109')
            ) .
            RCView::th(array('class' => 'header', 'style' => 'color:#800000;'),
                RCView::tt('openai_110')
            )
        ).
        $rows;
    $dialog_content = RCView::div(array(),
        RCView::table(array('width'=>'100%'),
            RCView::tr(array(),
                RCView::td(['class'=>'fs13 mb-4', 'style'=>'width:850px;'],
                    RCView::tt('openai_103') . " " .
                    RCView::a(['href'=>'javascript:;', 'class'=>'text-successrc', 'onclick'=>"simpleDialog('".RCView::tt_js('openai_069')."','".RCView::tt_js('openai_068')."');"],
                        RCView::fa('fa-solid fa-shield-halved mr-1').RCView::u(RCView::tt('openai_068')).RCView::tt('period')
                    )
                ) .
                RCView::td(array('class'=>'text-end'),
                    RCView::button(array('class'=>'btn btn-xs btn-rcgreen fs12 me-5', 'onclick'=>'generateSummary(this, "'.$report_id.'", "");'),
                        RCView::fa('fa-solid fa-wand-sparkles mr-1').RCView::tt('openai_064')
                    )
                )
            ) .
            RCView::tr(array(),
                RCView::td(array('colspan' => '2'),
                    RCView::table(array('style' => 'margin-top:20px;width:100%;border-bottom:1px solid #ccc;line-height:1.1;'), $rows)
                ))
        )
    );                              ;
} elseif (isset($_GET['action']) && $_GET['action'] == 'get_result') {
    $report_id = $_GET['report_id'];
    $field = $_GET['field'];
    if (isinteger($report_id)) {
        // Validate report_id for this project
        if (!DataExport::validateReportId(PROJECT_ID, $report_id)) exit;
        // Confirm user has access to this report
        if (DataExport::getReportNames($report_id, true) == null) exit;
    }
    $summary_details = AI::getReportAISummaryDetails($report_id);
    $custom_prompt = $summary_details['summary_prompt'][$field];
    if ($ai_services_enabled_global == '1') { // OpenAI Service
        $aiObj = new ChatGPTSummary();
    } elseif ($ai_services_enabled_global == '2') { // GeminiAI service
        $aiObj = new GeminiSummary();
    }
    $selectedResponse = $aiObj->getReportsSummaryResult(PROJECT_ID, $report_id, $field, $custom_prompt, false, 0.8);
    exit( $selectedResponse['errors'] ?? $selectedResponse['response'] );
} elseif (isset($_POST['action']) && $_POST['action'] == 'get_individual_result') {
    $report_id = $_POST['report_id'];
    $field = $_POST['field'];
    $summary_details = AI::getReportAISummaryDetails($report_id);
    $custom_prompt = (isset($_POST['prompt_pretext']) && $_POST['prompt_pretext'] != '') ? $_POST['prompt_pretext'] : Prompts::PROMPT_SUMMARIZE_DEFAULT;
    if ($ai_services_enabled_global == '1') { // OpenAI Service
        $aiObj = new ChatGPTSummary();
    } elseif ($ai_services_enabled_global == '2') { // GeminiAI service
        $aiObj = new GeminiSummary();
    }
    $selectedResponse = $aiObj->getReportsSummaryResult(PROJECT_ID, $report_id, $field, $custom_prompt, false, 0.8);
    exit( $selectedResponse['errors'] ?? $selectedResponse['response'] );
} elseif (isset($_POST['action']) && $_POST['action'] == 'get_temp_html') {
    global $Proj;
    $report_id = $_POST['report_id'];
    $field = $_POST['field'];
    $report_name = strip_tags(DataExport::getReportNames($report_id, false, true, true, PROJECT_ID));
    $dialog_title = RCView::span(['style'=>'color:#d31d90;'], RCView::fa('fa-solid fa-wand-sparkles') . " " . RCView::tt('openai_101',''))
                  . RCView::span(['class'=>'text-secondary font-weight-normal'], RCView::span(['class'=>'mx-2'], "&ndash;") . RCView::tt('report_builder_212','')) . " \"$report_name\"";
    $prompt_text = AI::getReportAISummaryTempDetails($report_id, $field);
    $dialog_content = AI::getSummarizeFieldBoxHTML($report_id, $field, $prompt_text);
} elseif (isset($_POST['action']) && $_POST['action'] == 'download_response_text') {
    $download_text = $_POST['response_text'];
    $report_id = $_POST['report_id'];
    $report_name = DataExport::getReportNames($report_id, false, true, true, PROJECT_ID);
    $filename = "AISummary_".substr(str_replace(" ", "", ucwords(preg_replace("/[^a-zA-Z0-9 ]/", "", html_entity_decode($report_name, ENT_QUOTES)))), 0, 30)
        ."_".$_POST['field']."_".date("Y-m-d").".doc";
    $filepath = APP_PATH_TEMP.$filename;

    // Create a file in temp directory to make it available for download
    $fp = fopen($filepath,"w");
    if ($fp !== false) {
        fputs($fp, $download_text);
        // Close connection
        fclose($fp);
    }
    print $filename; exit;
} elseif (isset($_GET['action']) && $_GET['action'] == 'download_file') {
    header('Pragma: anytextexeptno-cache', true);
    header("Content-type: application/msword");
    header("Content-Disposition: attachment; filename=\"".$_GET['filename']."\"");
    $filepath = APP_PATH_TEMP.$_GET['filename'];
    $fp = fopen($filepath, 'rb');
    print fread($fp, filesize($filepath));

    // Close file and delete it from temp directory
    fclose($fp);
    //unlink($filepath);
    exit;
} elseif (isset($_POST['action']) && $_POST['action'] == 'save_summary_setting') {
    $report_id = $_POST['report_id'];
    $report_name = DataExport::getReportNames($report_id, false, true, true, PROJECT_ID);

    if ($report_id != 'ALL') {
        $report_id = (int)$report_id;
        $condition = " AND report_id = '".$report_id."'";
    } else {
        $report_id = '';
        $condition = " AND report_id IS NULL";
    }
    if (!empty($_POST['report_id'])) {
        $dialog_title = RCView::tt('design_243');
        $sql = "SELECT * FROM redcap_reports_ai_prompts WHERE project_id = '".PROJECT_ID."'".$condition;
        $q = db_query($sql);
        $count = db_num_rows($q);
        if ($count > 0) { // Summary already exists
            $sql = "DELETE FROM redcap_reports_ai_prompts where project_id = '".PROJECT_ID."'".$condition;
            db_query($sql);
            foreach ($_POST['checked_fields'] as $i => $this_field) {
                if ($this_field == '' || !isset($Proj->metadata[$this_field])) continue;
                $sql = "INSERT INTO redcap_reports_ai_prompts (project_id, report_id, field_name, summary_prompt)
			            VALUES ('".PROJECT_ID."', ".checkNull($report_id).", '".db_escape($this_field)."', '".db_escape($_POST['custom_prompts'][$i])."')";
                db_query($sql);
            }
        } else { // Insert Summary setup to DB
            foreach ($_POST['checked_fields'] as $i => $this_field) {
                if ($this_field == '' || !isset($Proj->metadata[$this_field])) continue;
                $sql = "INSERT INTO redcap_reports_ai_prompts (project_id, report_id, field_name, summary_prompt)
			            VALUES ('".PROJECT_ID."', ".checkNull($report_id).", '".db_escape($this_field)."', '".db_escape($_POST['custom_prompts'][$i])."')";
                db_query($sql);
            }
        }
        $dialog_content = RCView::div(array('class'=>"text-successrc fs14"),
            RCView::fa('fa-solid fa-check mr-1').RCView::tt('custom_reports_21','')." \"".RCView::b(strip_tags($report_name))."\"".RCView::tt('period','')
        );
    }
} elseif (isset($_POST['action']) && $_POST['action'] == 'save_temp_prompt') {
    $report_id = $_POST['report_id'];
    $field = $_POST['field'];
    $condition = "project_id = '".PROJECT_ID."'";
    if ($report_id != 'ALL') {
        $report_id = (int)$report_id;
        $condition .= " AND report_id = '".$report_id."'";
    } else {
        $report_id = '';
        $condition .= " AND report_id IS NULL";
    }
    $condition .= " AND field_name = '".db_escape($field)."'";
    if (!empty($_POST['report_id'])) {
        $sql = "SELECT * FROM redcap_reports_ai_prompts WHERE ".$condition;
        $q = db_query($sql);
        $count = db_num_rows($q);
        if ($count > 0) { // Summary already exists
            $sql = "UPDATE redcap_reports_ai_prompts SET summary_prompt = '".db_escape($_POST['prompt_pretext'])."' WHERE ".$condition;
            db_query($sql);
            echo '1'; exit;
        } else { // Insert Summary setup to DB
            $sql = "INSERT INTO redcap_reports_ai_prompts (project_id, report_id, field_name, summary_prompt)
			            VALUES ('".PROJECT_ID."', ".checkNull($report_id).", '".db_escape($field)."', '".db_escape($_POST['prompt_pretext'])."')";
            db_query($sql);
            echo '1'; exit;
        }
    }
} elseif (isset($_GET['action']) && $_GET['action'] == 'toggleSummaryOptions') {
    $summary_details = AI::getReportAISummaryDetails($_POST['report_id']);
    if (!empty($summary_details)) { // Summary already exists
        $show_view_summary = 1;
    } else { // Summary setup not yet created
        $show_view_summary = 0;
    }
    print json_encode_rc(array('show_view_summary' => $show_view_summary)); exit;
} else {
    $report_id = $_POST['report_id'];
    $summary_details = AI::getReportAISummaryDetails($report_id);

    $checked_fields = [];
    if (!empty($summary_details['summary_fields'])) {
        $checked_fields = $summary_details['summary_fields'];
        foreach ($checked_fields as $key => $this_field) {
            if (!isset($Proj->metadata[$this_field])) {
                unset($checked_fields[$key]);
            }
        }
        $checked_fields = array_flip(array_unique($checked_fields));
    }

    // Loop through all fields and build HTML table (exclude Descriptive fields)
    $t = "";
    $report = DataExport::getReports($report_id);
    foreach ($report['fields'] as $this_field) {
        $attr = $Proj->metadata[$this_field];
        $element_type = $attr['element_type'];
        $validation_type = $attr['element_validation_type'];
        $isFreeFormTextField = (($element_type == 'text' && $validation_type == '') || $element_type == 'textarea');
        if (!$isFreeFormTextField || $this_field == $Proj->table_pk) continue;
        // Add the "checked" attribute if field already exists in report
        $checked = (isset($checked_fields[$this_field])) ? "checked" : "";
        $promptText = (isset($summary_details['summary_prompt'][$this_field]) && trim($summary_details['summary_prompt'][$this_field]) != '') ? $summary_details['summary_prompt'][$this_field] : Prompts::PROMPT_SUMMARIZE_DEFAULT;
        // Add field row
        $rows[$attr['form_name']][] = RCView::tr(array(),
            RCView::td(array('class' => 'data nowrap', 'style' => 'width:30px;text-align:center;padding-top:4px; vertical-align: top;'),
                RCView::checkbox(array('class' => "frm-" . $attr['form_name'] . " field-select", 'name' => $this_field, 'onclick' => "processFieldChecked(this);", $checked => $checked))
            ) .
            RCView::td(array('class' => 'data', 'style' => 'padding:4px 0 2px 5px;'),
                RCView::div(array(), RCView::b(array(), $this_field) .RCView::span(array(), ' "' . RCView::i(array(), RCView::escape(strip_tags(label_decode($attr['element_label'])))) . '"')) .
                RCView::div(array('class'=>'prompt-input', 'style' => (isset($checked_fields[$this_field]) ? '' : 'display: none;')),
                    RCView::textarea(array('placeholder' => RCView::tt_attr('openai_113'), 'name' => "custom-prompt-".$this_field, 'id' => "custom-prompt-".$this_field, 'class'=>"x-form-field fs11", 'style'=>"line-height:1.2;width:95%;height:35px;resize:auto;"), $promptText)
                )
            )
        );
    }

    foreach ($rows as $form_name => $trs) {
        $t .= RCView::tr(array(),
            RCView::td(array('class' => 'header', 'style' => 'width:30px;'),
                ''
            ) .
            RCView::td(array('class' => 'header', 'valign' => 'middle', 'style' => 'color:#800000;font-size:14px;padding-top:0;'),
                RCView::br() .
                // Form name
                RCView::escape(strip_tags(label_decode($Proj->forms[$form_name]['menu']))) .
                // Select all
                RCView::span(array('style' => 'color:#777;margin-left:50px;font-weight:normal;font-size:12px;'),
                    "(" .
                    RCView::a(array('href' => 'javascript:;', 'style' => 'font-size:11px;margin:0 3px;text-decoration:underline;font-weight:normal;', 'onclick' => "selectSummaryFieldsForForm('{$form_name}',true);"), $lang['data_export_tool_52']) .
                    "/" .
                    // Deselect all
                    RCView::a(array('href' => 'javascript:;', 'style' => 'font-size:11px;margin:0 3px;text-decoration:underline;font-weight:normal;', 'onclick' => "selectSummaryFieldsForForm('{$form_name}',false);"), $lang['data_export_tool_53']) .
                    ")"
                )
            )
        );
        foreach ($trs as $tr) {
            $t .= $tr;
        }
    }
    if ($t == '') {
        $t = '<tr><td class="error no-fields" style="text-align: center;">'.RCView::tt('openai_111').'</td></tr>';
    }

    $dialog_title = RCView::span(['style'=>'color:#d31d90;'], RCView::fa('fa-solid fa-wand-sparkles') . " " . RCView::tt('openai_066',''))
                  . RCView::span(['class'=>'text-secondary font-weight-normal'], RCView::span(['class'=>'mx-2'], "&ndash;") . RCView::tt('report_builder_212','')) . " \"".RCView::escape($report['title'])."\"";

    $dialog_content =
        RCView::div(array('class'=>'fs13 mb-2'),
            RCView::tt('openai_058')
        ) .
        RCView::div(array('class'=>'fs14 mt-4 mb-2 font-weight-bold text-dangerrc'),
            RCView::tt('openai_112')
        ) .
        RCView::div(array('style' => ''),
            RCView::div(array('class' => 'mt-2'),
                // Table
                RCView::table(array('cellspacing' => '0', 'class' => 'form_border', 'style' => 'table-layout:fixed;width:100%;'), $t)
            )
        );
}
// Output JSON response
print json_encode_rc(array('title'=>$dialog_title, 'content'=>$dialog_content));