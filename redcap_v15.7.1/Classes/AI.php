<?php

class AI
{
    public static $serviceAzureOpenAI = "AzureOpenAI";
    public static $serviceGemini = "Gemini";
    public static $callTypeMLMTranslator = "MLMTranslator";
    public static $callTypeWritingTools = "EditorWritingTools";
    public static $callTypeDataSummary = "ReportDataSummary";

    // Get the URL and API Key of the AI service being used (whether project-level or system-level)
    public static function getServiceAttributes()
    {
        $openai_api_key = '';
        $openai_endpoint_url = '';
        $openai_api_version = '';

        $geminiai_api_key = '';
        $geminiai_api_version = '';
        $geminiai_api_model = '';
        $return_list = [];
        // Must be enabled globally first
        if ($GLOBALS['ai_services_enabled_global'] == '1') {
            // First see if the project has these defined
            if (defined("PROJECT_ID") && PROJECT_ID != null) {
                $Proj = new Project(PROJECT_ID);
                $openai_api_key = $Proj->project['openai_api_key_project'];
                $openai_endpoint_url = $Proj->project['openai_endpoint_url_project'];
                $openai_api_version = $Proj->project['openai_api_version_project'];
            }
            // If no project-level values exist, then use system-level
            if ($openai_api_key == '' && $openai_endpoint_url == '') {
                $openai_api_key = $GLOBALS['openai_api_key'];
                $openai_endpoint_url = $GLOBALS['openai_endpoint_url'];
                $openai_api_version = $GLOBALS['openai_api_version'];
            }
            // Ensure URL ends with a slash
            if ($openai_endpoint_url != '' && substr($openai_endpoint_url, -1) != '/') {
                $openai_endpoint_url .= '/';
            }
            $return_list = [
                'service_enabled' => $GLOBALS['ai_services_enabled_global'],
                'service_details' => [$openai_endpoint_url, $openai_api_key, $openai_api_version]
            ];
        } else if ($GLOBALS['ai_services_enabled_global'] == '2') {
            // First see if the project has these defined
            if (defined("PROJECT_ID") && PROJECT_ID != null) {
                $Proj = new Project(PROJECT_ID);
                $geminiai_api_key = $Proj->project['geminiai_api_key_project'];
                $geminiai_api_version = $Proj->project['geminiai_api_version_project'];
                $geminiai_api_model = $Proj->project['geminiai_api_model_project'];
            }
            // If no project-level values exist, then use system-level
            if ($geminiai_api_key == '' && $geminiai_api_version == '') {
                $geminiai_api_key = $GLOBALS['geminiai_api_key'];
                $geminiai_api_model = $GLOBALS['geminiai_api_model'];
                $geminiai_api_version = $GLOBALS['geminiai_api_version'];
            }
            $return_list = [
                'service_enabled' => $GLOBALS['ai_services_enabled_global'],
                'service_details' => [$geminiai_api_key, $geminiai_api_model, $geminiai_api_version]
            ];
        }

        // Return values as array
        return $return_list;
    }

    // Return array of summary prompt and summary fields for report
    public static function getReportAISummaryDetails($report_id) {
        $return = [];
        if ($report_id != 'ALL' && isinteger($report_id)) {
            $condition = " AND report_id = '".$report_id."'";
        } else {
            $condition = " AND report_id IS NULL";
        }
        $sql2 = "SELECT * FROM redcap_reports_ai_prompts WHERE project_id = '".PROJECT_ID."'".$condition;
        $q2 = db_query($sql2);
        if (db_num_rows($q2) > 0) {
            while ($row2 = db_fetch_assoc($q2)) {
                $fields[] = $row2['field_name'];
                $prompts[$row2['field_name']] = $row2['summary_prompt'];
            }
            $return['summary_fields'] = $fields;
            $return['summary_prompt'] = $prompts;
        }
        return $return;
    }

    public static function getSummarizeFieldBoxHTML($report_id, $field, $prompt_text = '')
    {
        global $Proj;

        if (!isset($Proj->metadata[$field])) return RCView::tt('global_01','');
        $field_label = strip_tags($Proj->metadata[$field]['element_label']);

        $prompt_text_stored = $prompt_text = trim($prompt_text ?? '');
        $btnClass = ($prompt_text == '') ? 'btn-warning' : 'btn-defaultrc';
        if ($prompt_text == '') {
            $prompt_text = \Vanderbilt\REDCap\Classes\OpenAI\Prompts::PROMPT_SUMMARIZE_DEFAULT;
        }

        $html = '';

        $html .= RCView::div(array('id' => 'prompt-form-'.$field, 'class'=>'show-hide', 'style'=>'max-width:770px; '),
            RCView::div(['class'=>'fs13 mb-4'],
                RCView::tt('openai_103') . " " .
                RCView::a(['href'=>'javascript:;', 'class'=>'text-successrc', 'onclick'=>"simpleDialog('".RCView::tt_js('openai_069')."','".RCView::tt_js('openai_068')."');"],
                    RCView::fa('fa-solid fa-shield-halved mr-1').RCView::u(RCView::tt('openai_068')).RCView::tt('period')
                )
            ) .
            // Field label
            RCView::ol(['class'=>'mb-3'],
                RCView::li(['class'=>'mb-3 fs14'],
                    RCView::tt('openai_104','') . " " .
                    RCView::span(['class'=>'text-dangerrc'],
                        "\"" . RCView::span(['class'=>'boldish'], $field_label) . "\" ($field)"
                    )
                ) .
                // Action text with Modify Action button
                RCView::li(['class'=>'mb-3 fs14'],
                    RCView::tt('openai_105','') . " " .
                    RCView::span(['id'=>'prompt-label-'.$field, 'class'=>'text-primaryrc boldish mr-2'], '"'.$prompt_text.'"') .
                    RCView::button(['class'=>'btn btn-xs btn-link fs12 text-secondary', 'onclick'=>"$('#summary-pretext-div-{$field}').show();"], RCView::fa('fa-solid fa-pen-to-square mr-1') . RCView::tt('design_169')) .
                    // Hidden textarea to modify the action text
                    RCView::div(['id'=>'summary-pretext-div-'.$field, 'class'=>'mt-2 mb-2', 'style'=>'display:none;'],
                        RCView::div(['class'=>'fs12 text-secondary clearfix', 'style'=>'width:95%;'],
                            RCView::div(['class'=>'float-left'], RCView::tt('openai_102')).
                            RCView::div(['class'=>'float-right nowrap', 'style'=>'position:relative;top:20px;'],
                                RCView::button(array('id'=>'save-btn-'.$field, 'style' => 'color:#000066;', 'class'=>'ml-2 fs11 btn btn-xs '.$btnClass, 'onclick'=>'saveTempSummarizePrompt("'.$report_id.'", "'.$field.'")'),
                                    '<i class="fas fa-save"></i> '.RCView::tt('openai_061')
                                )
                            )
                        ) .
                        RCView::textarea(array('id'=>'summary-pretext-'.$field, 'class' => 'prompt-pretext-box x-form-field notesbox', 'style'=>'width:470px;height:50px;'), $prompt_text) .
                        RCView::hidden(array('class' => 'prompt-pretext-stored-value', 'value' => $prompt_text_stored))
                    )
                )
            ) .
            // Execute button
            RCView::div(['class'=>'ml-3 mt-4 mb-3'],
                RCView::button(array('class'=>'btn btn-sm fs14 btn-defaultrc boldish', 'style'=>'color:#d31d90;', 'onclick'=>"processTempSummarizeResponse('$report_id','$field');"),
                    RCView::fa('fa-solid fa-wand-sparkles mr-1').RCView::tt('openai_106')
                )
            )
//            . RCView::div(array(),
//                RCView::table(array('width'=>'100%;', 'cellpadding'=>'0', 'cellspacing'=>'0', 'class'=>'form_border'),
//                    RCView::tr(array(),
//                        RCView::td(array('style'=>'width:70%; font-weight:bold;', 'class'=>'labelrc'),
//                            RCView::textarea(array('id'=>'summary-pretext-'.$field, 'placeholder' => 'Enter Summary Prompt', 'class' => 'prompt-pretext-box', 'style'=>'width: 95%;'), $prompt_text) .
//                            RCView::hidden(array('class' => 'prompt-pretext-stored-value', 'value' => $prompt_text_stored))
//                        ) .
//                        RCView::td(array('class'=>'labelrc'),
//                            RCView::table(array('cellpadding'=>'3'),
//                                RCView::tr(array('class' => 'form-control-custom'),
//                                    RCView::td(array(),
//                                        RCView::button(array('type'=>'button', 'id'=>'save-btn-'.$field, 'style' => 'color:#000066;font-size:12px;', 'class'=>$btnClass, 'onclick'=>'saveTempSummarizePrompt("'.$report_id.'", "'.$field.'")'), '<i class="fas fa-save"></i> '.RCView::tt('openai_061'))
//                                    )
//                                ).
//                                (!$showToggleBox ? '' : RCView::tr(array('class' => 'form-control-custom'),
//                                    RCView::td(array(),
//                                        RCView::button(array('type'=>'button', 'id'=>'summarize-btn-'.$field, 'style' => 'color:#000066;font-size:12px;', 'class'=>'jqbuttonmed ui-button ui-corner-all ui-widget', 'onclick'=>'processTempSummarizeResponse("'.$report_id.'", "'.$field.'")'), '<i class="fas fa-list"></i> Summarize')
//                                    )
//                                ))
//                            )
//                        )
//                    )
//                )
//            )
        );
        $result = RCView::div(array('id' => 'result-box-'.$field, 'style'=>'font-size:11px;padding-left:8px;border:1px solid #CCCCCC; background-color:#f6f6f6;max-width:100%;display:none;margin-top:25px;'),
            RCView::div(array(),
                RCView::table(array('width'=>'100%;', 'cellpadding'=>'0', 'cellspacing'=>'0'),
                    RCView::tr(array('class' => 'form-control-custom'),
                        RCView::td(array('class'=>'boldish fs14 my-2 pt-2', 'style'=>'color:#B00000;'),
                            RCView::tt('openai_107')
                        ) .
                        RCView::td(array('style' => 'text-align: right;font-size:14px;display:none;padding-top:5px;padding-right:8px;', 'id'=>'action-buttons-'.$field),
                            RCView::a(array('href'=>"javascript:;", 'title'=>RCView::tt_attr('design_121'), 'style'=>"outline: none;", 'onClick'=>"downloadAIResponse('".$field."', '".$report_id."');"), '<i style="color: green; font-size: 14px;" class="fas fa-file-download"></i>') . " | " .
                            RCView::a(array('href'=>"javascript:;", 'title'=>RCView::tt_attr('report_builder_46'), 'style'=>"outline: none;", 'onClick'=>"copyResponseToClipboard('".$field."');"), '<i id="field-copy-status-'.$field.'" style="color: green; font-size: 14px;" class="fas fa-copy"></i>')
                        )
                    ).
                    RCView::tr(array('class' => 'form-control-custom'),
                        RCView::td(array('style'=>'width:80%; padding: 5px 8px 5px 0; font-size: 13px;', 'colspan'=>'2', 'id'=>'summary-result-'.$field),
                            '<i class="fas fa-spinner fa-spin test-progress"></i> '.RCView::tt('openai_067')
                        )
                    )
                )
            )
        );
        return $html . $result;
    }

    // Return array of summary prompt and summary fields for "Stats & charts" page
    public static function getReportAISummaryTempDetails($report_id, $field = '')
    {
        $prompts = [];
        if ($report_id != 'ALL') {
            $condition = " AND report_id = '".db_escape($report_id)."'";
        } else {
            $condition = " AND report_id IS NULL";
        }
        if ($field != '') {
            $condition .= " AND field_name = '".db_escape($field)."'";
        }
        $sql2 = "SELECT * FROM redcap_reports_ai_prompts WHERE project_id = '".PROJECT_ID."'".$condition;
        $q2 = db_query($sql2);
        if (db_num_rows($q2) > 0) {
            while ($row2 = db_fetch_assoc($q2)) {
                $prompts[$row2['field_name']] = $row2['summary_prompt'];
            }
        }
        return (($field != '') ? ($prompts[$field] ?? '') : $prompts);
    }

    // Log the AI call in a db table for stats and logging purposes
    public static function logApiCall($service, $type, $content, $response, $project_id=null)
    {
        if ($content === null) $content = "";
        $sql = "insert into redcap_ai_log (ts, service, type, project_id, user_id, num_chars_sent, num_words_sent, num_chars_received, num_words_received) 
                values (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $params = [date('Y-m-d H:i:s'), $service, $type, $project_id, UI_ID, mb_strlen($content), str_word_count($content), mb_strlen($response), str_word_count($response)];
        return db_query($sql, $params);
    }

    // Check if AI service connection details set for openAI/GeminiAI at system-level or project-level
    public static function isServiceDetailsSet() {
        $aiDetailsSet = false;
        $service_attr = self::getServiceAttributes();

        if (isset($service_attr['service_enabled']) && $service_attr['service_enabled'] == '1') { // OpenAI Service enabled
            list ($openai_endpoint_url, $openai_api_key, $openai_api_version) = $service_attr['service_details'];
            $aiDetailsSet = ($openai_endpoint_url != '' && $openai_api_version != '');
        } elseif (isset($service_attr['service_enabled']) && $service_attr['service_enabled'] == '2') { // Gemini AI Service enabled
            list ($geminiai_api_key, $geminiai_api_version, $geminiai_api_model) = $service_attr['service_details'];
            $aiDetailsSet = ($geminiai_api_key != '' && $geminiai_api_version != '' && $geminiai_api_model != '');
        }
        return $aiDetailsSet;
    }
}
