<?php

use MultiLanguageManagement\MultiLanguage;
use REDCap\Context;

class PdfSnapshot
{
    // Render the initial eConsent setup page
    public function renderSetup()
    {
        global $lang;
        // JS/CSS
        addLangToJS(['econsent_02','survey_437','survey_1173','econsent_16','econsent_09','econsent_142','econsent_08','control_center_439', 'docs_77',
                     'econsent_38','econsent_42','econsent_32','econsent_08', 'econsent_71', 'econsent_99', 'control_center_4878', 'control_center_4879',
                     'econsent_108', 'econsent_109', 'econsent_43', 'global_53', 'econsent_96', 'econsent_129', 'econsent_150', 'econsent_169', 'econsent_170',
                     'econsent_178']);
        loadJS('PdfSnapshotSetup.js');
        loadCSS('PdfSnapshotSetup.css');
        // Video link
        $videoLink = RCView::div(['class'=>'float-right font-weight-normal fs13'],
            RCView::fa('fas fa-film mr-1') .
            RCView::a(array('href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"popupvid('econsent01.mp4','".RCView::escape($lang['econsent_184'])."');"),
                $lang['global_80'] . " " . $lang['econsent_184']
            )
        );
        // Title and instructions
        renderPageTitle(RCView::tt('econsent_35').$videoLink);
        // Tabs
        $ec = new Econsent();
        $ec->renderTabs();
        // Instructions
        print RCView::p(['class' => 'mt-1 mb-4 pb-1', 'style' => 'max-width:900px;'], RCView::tt('econsent_36'));
        // Table placeholder
        print RCView::div(['id' => 'record-snapshot-table-parent'], RCView::table(['id' => 'record-snapshot-table'], ''));
    }

    // Save snapshot
    public function saveSetup($snapshot_id=null)
    {
        global $Proj;
        // Get existing snapshot, if exists, else get defaults
        if (isinteger($snapshot_id)) {
            $snapshot = $this->getSnapshots($Proj->project_id, false, false, $snapshot_id);
        } else {
            $snapshot_id = null;
            $snapshot = getTableColumns('redcap_pdf_snapshots');
        }
        // Apply new values on top of array
        foreach (array_keys($snapshot) as $key) {
            if (isset($_POST[$key])) {
                // Get value from Post
                $val = $_POST[$key];
                // Parse the select instruments/events string
                if ($key == 'selected_forms_events') {
                    $val = $Proj->convertSelectedFormsEventsToBackend($val);
                }
                // Set in array
                $snapshot[$key] = $val;
            }
        }
        if ($snapshot_id == null) {
            unset($snapshot['snapshot_id']);
            $snapshot['project_id'] = PROJECT_ID;
            $snapshot['active'] = '1';
        }
        $snapshot['custom_filename_prefix'] = strip_tags($snapshot['custom_filename_prefix']);
        if ($snapshot['trigger_logic'] != '') {
            $snapshot['trigger_surveycomplete_survey_id'] = null;
            $snapshot['trigger_surveycomplete_event_id'] = null;
        } else {
            list ($snapshot['trigger_surveycomplete_survey_id'], $snapshot['trigger_surveycomplete_event_id']) = explode("-", $snapshot['trigger_surveycomplete_survey_id'], 2);
            if (!isinteger($snapshot['trigger_surveycomplete_event_id'])) $snapshot['trigger_surveycomplete_event_id'] = null;
        }
        $snapshot['pdf_save_to_file_repository'] = (isset($snapshot['pdf_save_to_file_repository']) && in_array($snapshot['pdf_save_to_file_repository'], ['on', '1'], true)) ? '1' : '0';
        $snapshot['pdf_compact'] = (isset($snapshot['pdf_compact']) && in_array($snapshot['pdf_compact'], ['on', '1'], true)) ? '1' : '0';
        $snapshot['pdf_save_translated'] = (isset($snapshot['pdf_save_translated']) && in_array($snapshot['pdf_save_translated'], ['on', '1'], true)) ? '1' : '0';
        $snapshot['pdf_save_to_event_id'] = (isset($snapshot['pdf_save_to_event_id']) && $snapshot['pdf_save_to_field'] != '')
                                            ? $snapshot['pdf_save_to_event_id']
                                            : ($snapshot['pdf_save_to_field'] == '' ? null : $Proj->firstEventId);
        if ($snapshot['pdf_save_to_event_id'] == '') $snapshot['pdf_save_to_event_id'] = null;
        if ($snapshot['pdf_save_to_event_id'] == '') $snapshot['pdf_save_to_event_id'] = null;
        // Save the snapshot
        if ($snapshot_id == null) {
            $sql = "insert into redcap_pdf_snapshots (".implode(", ", array_keys($snapshot)).") 
				    values (".prep_implode($snapshot, true, true).")";
        } else {
            $updateSql = [];
            foreach ($snapshot as $col=>$val) {
                if ($col == 'snapshot_id' || $col == 'project_id') continue;
                $val = ($val === null) ? "null" : "'".db_escape($val)."'";
                $updateSql[] = "$col = $val";
            }
            $updateSql = implode(", ", $updateSql);
            $sql = "update redcap_pdf_snapshots set $updateSql where snapshot_id = $snapshot_id";
        }
        if (db_query($sql)) {
            // Success
            $snapshot_id_new = ($snapshot_id == null) ? db_insert_id() : $snapshot_id;
            // Log the event
            Logging::logEvent($sql, "redcap_pdf_snapshots", "MANAGE", $snapshot_id_new, "snapshot_id = $snapshot_id_new", ($snapshot_id == null ? "Create trigger for PDF Snapshot (snapshot_id = $snapshot_id_new)" : "Modify trigger for PDF Snapshot (snapshot_id = $snapshot_id_new)"));
            print ($snapshot_id == null ? RCView::tt('econsent_105','') : RCView::tt('econsent_106',''));
        } else {
            // Error
            print '0';
        }

    }

    // Display AJAX output for Edit PDF Snapshot Setup dialog
    public function editSetup($snapshot_id=null)
    {
        global $Proj, $lang;

        // Get this snapshot, if exists
        if (isinteger($snapshot_id)) {
            $snapshot = $this->getSnapshots($Proj->project_id, false, false, $snapshot_id);
        } else {
            $snapshot = getTableColumns('redcap_pdf_snapshots');
        }

        // Create list of all surveys/event instances as array to use for looping below and also to feed a drop-down
        $surveyDD = array(''=>'--- '.RCView::getLangStringByKey("survey_404").' ---');
        foreach ($Proj->surveys as $this_survey_id => $attr) {
            // Add event-event survey
            $title = strip_tags($Proj->surveys[$this_survey_id]['title']);
            $form_display = strip_tags($Proj->forms[$Proj->surveys[$this_survey_id]['form_name']]["menu"]);
            $option_label = "\"$title\"";
            if ($title != $form_display) $option_label .= " [$form_display]";
            if ($Proj->longitudinal) $option_label .= " - [Any Event]";
            $surveyDD["$this_survey_id-"] = $option_label;
        }
        if ($Proj->longitudinal) {
            foreach ($Proj->eventsForms as $this_event_id => $forms) {
                // Go through each form and see if it's a survey
                foreach ($forms as $form) {
                    // Get survey_id
                    $this_survey_id = isset($Proj->forms[$form]['survey_id']) ? $Proj->forms[$form]['survey_id'] : null;
                    // Only display surveys, so ignore if does not have survey_id
                    if (!is_numeric($this_survey_id)) continue;
                    // Add form, event_id, and survey_id to drop-down array
                    $title = strip_tags($Proj->surveys[$this_survey_id]['title']);
                    $event = $Proj->eventInfo[$this_event_id]['name_ext'];
                    $form_display = strip_tags($Proj->forms[$form]["menu"]);
                    $option_label = "\"$title\"";
                    if ($title != $form_display) $option_label .= " [$form_display]";
                    if ($Proj->longitudinal) $option_label .= " - $event";
                    // Add survey to array
                    $surveyDD["$this_survey_id-$this_event_id"] = $option_label;
                }
            }
        }

        $pdfSaveEvents = [''=>$lang['survey_1306']];
        foreach ($Proj->eventInfo as $thisEventId=>$attr) {
            $pdfSaveEvents[$thisEventId] = $attr['name_ext'];
        }
        $pdfSaveFields = Form::getFieldDropdownOptions(true, false, false, false, '', true, true, false, 'file', $lang['econsent_76']);
        $pdfSaveFieldsEmpty = count($pdfSaveFields) <= 1;
        $pdfSaveFieldsDisabled = $pdfSaveFieldsEmpty ? "disabled" : "";

        $pdf_save_to_field_checkbox_checked = ($snapshot['pdf_save_to_field'] == '') ? "" : "checked";
        $pdf_save_to_file_repository_checkbox_checked = ($snapshot['pdf_save_to_file_repository'] == '1' || $pdfSaveFieldsEmpty) ? "checked" : "";
        $pdf_compact_checkbox_checked = ($snapshot['pdf_compact'] == '0') ? "" : "checked";

        if ($snapshot['custom_filename_prefix'] == null) $snapshot['custom_filename_prefix'] = 'pid[project-id]_form[instrument-label]_id[record-name]'; // default filename prefix

        $pdf_save_translated_checked = $snapshot['pdf_save_translated'] ? "checked" : "";

        $html = "";
        $html .= RCView::div([],
            RCView::div(['class'=>'mb-3'],
                RCView::tt("econsent_101")
            ) .
            // When survey is completed
            RCView::div(array('class'=>'well py-3 fs14 text-dangerrc boldish'),
                RCView::tt("econsent_173",'span',['class'=>'mr-2']) . " " .
                RCView::input(array('name'=>'name', 'class'=>'x-form-text x-form-field', 'style'=>'width:400px;', 'maxlength'=>'50', 'value'=>($snapshot['name']??""), 'placeholder'=>RCView::tt_attr('econsent_174')))
            ) .
            // When survey is completed
            RCView::div(array('class'=>'well'),
                RCView::div(array('class'=>'fs14 mb-3 text-dangerrc'),
                    RCView::tt("econsent_140",'span',['class'=>'font-weight-bold']) . " " .
                    RCView::tt("econsent_141",'span',['class'=>''])
                ).
                RCView::div([],
                    RCView::tt("econsent_114",'span',['class'=>'fs14 boldish']) .
                    // Drop-down of surveys/events
                    RCView::div(['style'=>'margin-top:3px;'],
                        RCView::select(array('name'=>"trigger_surveycomplete_survey_id",'id'=>"trigger_surveycomplete_survey_id",'class'=>'snapshot-survey-list-dropdown x-form-text x-form-field','style'=>'font-size:11px;width:93%;',
                            'onchange'=>"$('#pdfsnapshot-surveycomplete').prop('checked', (this.value.length > 0) );"), $surveyDD, $snapshot['trigger_surveycomplete_survey_id']."-".$snapshot['trigger_surveycomplete_event_id'], 200)
                    )
                ) .
                // AND/OR drop-down list for conditions
                RCView::div(array('class'=>'mt-2 mb-2 ml-2 text-dangerrc'),
                    " -- ".RCView::getLangStringByKey("global_46")." -- "
                ) .
                // When logic becomes true
                RCView::div(array('class'=>''),
                    RCView::tt("econsent_113",'span',['class'=>'fs14 boldish']) .
                    RCView::a(array('href'=>'javascript:;','class'=>'opacity65','style'=>'margin-left:30px;text-decoration:underline;font-size:10px;','onclick'=>"helpPopup('5','category_33_question_1_tab_5')"), RCView::tt("survey_527")) .
                    RCView::div(['style'=>'margin-top:3px;'],
                        RCView::textarea(array('name'=>"trigger_logic",'id'=>"pdfsnapshotlogic",'class'=>'x-form-field',
                            'onfocus'=>'openLogicEditor($(this))',
                            'style'=>'line-height:1.1;font-size:12px;width:93%;height:50px;resize:auto;padding:3px 6px;',
                            'onkeydown' => 'logicSuggestSearchTip(this, event);',
                            'onblur' => "var val = this; setTimeout(function() { logicHideSearchTip(val); this.value=trim(val.value); if(val.value.length > 0) { $('#pdfsnapshot-logic').prop('checked',true); } if(!checkLogicErrors(val.value,1,true)){validate_auto_invite_logic($(val));} }, 0);"),
                            $snapshot['trigger_logic']) .
                        logicAdd("pdfsnapshotlogic") .
                        RCView::div(array('id'=>'pdfsnapshotlogic_Ok', 'style'=>'font-weight:bold;height:12px;margin-top:2px;', 'class'=>'logicValidatorOkay'), ' ') .
                        RCView::div(array('style'=>'font-family:tahoma;font-size:10px;color:#888;'),
                            ($Proj->longitudinal ? "(e.g., [enrollment_arm_1][age] > 30 and [enrollment_arm_1][sex] = \"1\")" : "(e.g., [age] > 30 and [sex] = \"1\")")
                        )
                    )
                )
            ) .
            // Scope: Select instruments/events
            RCView::div(array('class'=>'well'),
                RCView::div(array('class'=>'mb-2 text-dangerrc fs14'),
                    RCView::tt('econsent_19','span',['class'=>'font-weight-bold'])
                ).
                RCView::div(array('class'=>'mb-3 fs12 lineheight12'),
                    RCView::tt('econsent_102')
                ) .
                RCView::div(array('class'=>''),
                    RCView::div(array('class'=>'x-form-text  x-form-field','style'=>'font-weight:normal;width:93%;','onclick'=>"openExcludeFormsEvents();"),
                        RCView::img(array('src'=>'pencil_small2.png', 'style'=>'cursor:pointer;')).
                        RCView::input(array('id'=>'selected_forms_events', 'name'=>'selected_forms_events', 'style'=>'cursor:default;font-size:12px;', 'placeholder'=>RCView::tt_attr('econsent_145'),
                                'value'=>$Proj->convertSelectedFormsEventsFromBackend($snapshot['selected_forms_events']),'disabled'=>'disabled')
                        )
                    ) .
                    $Proj->renderSelectedFormsEvents($snapshot['selected_forms_events'], true) .
                    RCView::div(array('class'=>'mt-1 mb-1 text-primaryrc fs12'),
                        RCView::fa('fa-regular fa-lightbulb mr-1'). $lang['econsent_107']
                    ) .
                    // Compact PDF?
                    RCView::label(array('class'=>'mt-3 pt-1 mb-0 d-block', 'for'=>'pdf_compact'),
                        RCView::checkbox(array('id'=>'pdf_compact', 'name'=>'pdf_compact', $pdf_compact_checkbox_checked=>$pdf_compact_checkbox_checked)) .
                        RCView::tt('econsent_112')
                    ) .
                    // Store translated PDF?
                    RCView::label(['class'=>'mt-1 mb-0 d-block', 'for'=>'pdf_save_translated'],
                        RCView::checkbox(array('name'=>'pdf_save_translated', 'id'=>'pdf_save_translated', 'style'=>'margin-right:2px;', $pdf_save_translated_checked=>$pdf_save_translated_checked)) .
                        RCView::tt('survey_1370') .
                        RCView::tt('survey_1371','span',['class'=>'ml-1'])
                    )
                )
            ) .
            // Storage location
            RCView::div(array('class'=>'well pb-3'),
                RCView::div(array('class'=>'font-weight-bold mb-2 text-dangerrc fs14'),
                    RCView::tt('econsent_143') . " " .
                    RCView::tt('econsent_16')
                ) .
                RCView::label(array('class'=>'mb-1 d-block', 'for'=>'pdf_save_to_file_repository'),
                    RCView::checkbox(array('class'=>'', 'id'=>'pdf_save_to_file_repository', 'name'=>'pdf_save_to_file_repository', $pdf_save_to_file_repository_checkbox_checked=>$pdf_save_to_file_repository_checkbox_checked)) .
                    RCView::tt('econsent_103', 'span', ['class'=>'boldish']) .
                    ($GLOBALS['pdf_econsent_filesystem_type'] != '' && $Proj->project['store_in_vault_snapshots_containing_completed_econsent'] ? "<b class='text-dangerrc'>*</b>" : "")
                ) .
                RCView::label(array('class'=>'mb-1 d-block '.($pdfSaveFieldsEmpty ? 'text-tertiary' : ''), 'for'=>'pdf_save_to_field_checkbox'),
                    RCView::checkbox(array('id'=>'pdf_save_to_field_checkbox', 'name'=>'pdf_save_to_field_checkbox', 'onclick'=>"if (!$(this).prop('checked')) $('select[name=pdf_save_to_field]').val('');", $pdf_save_to_field_checkbox_checked=>$pdf_save_to_field_checkbox_checked, $pdfSaveFieldsDisabled=>$pdfSaveFieldsDisabled)) .
                    RCView::tt('econsent_104', 'span', ['class'=>'mr-2 boldish']) .
                    RCView::select(array('name'=>'pdf_save_to_event_id', 'class'=>'x-form-text x-form-field fs12 mr-1', 'style'=>'max-width:150px;'.($Proj->longitudinal ? "" : "display:none;"), $pdfSaveFieldsDisabled=>$pdfSaveFieldsDisabled),
                        $pdfSaveEvents, $snapshot['pdf_save_to_event_id'], 300) .
                    RCView::select(array('name'=>'pdf_save_to_field', 'class'=>'x-form-text x-form-field fs12', 'style'=>'max-width:350px;', 'onchange'=>"$('#pdf_save_to_field_checkbox').prop('checked',($(this).val()!=''));", $pdfSaveFieldsDisabled=>$pdfSaveFieldsDisabled),
                        $pdfSaveFields, $snapshot['pdf_save_to_field'])
                ) .
                (!($GLOBALS['pdf_econsent_filesystem_type'] != '' && $Proj->project['store_in_vault_snapshots_containing_completed_econsent']) ? "" :
                    RCView::div(array('class'=>'mt-3 text-tertiary fs12 lineheight10'),
                        RCView::tt('econsent_183')
                    )
                )
            ) .
            // Filename prefix
            RCView::div(array('class'=>'well'),
                RCView::div(array('class'=>'mb-2 fs14 font-weight-bold text-dangerrc'),
                    RCView::tt('econsent_144') . " " . RCView::tt('econsent_121')
                ) .
                RCView::div(array('class'=>'mb-3 fs13 lineheight12'),
                    RCView::tt('econsent_122')
                ) .
                RCView::div(array('class'=>'input-group', 'style'=>'width:97%;'),
                    RCView::tt('docs_19', 'span', ['class'=>'input-group-text py-1 px-2 boldish fs12']) .
                    RCView::text(['name'=>'custom_filename_prefix', 'class'=>'form-control py-1 px-2 fs12', 'style'=>'text-align:end;', 'value'=>$snapshot['custom_filename_prefix']]) .
                    RCView::span(['class'=>'input-group-text py-1 px-2 fs12', 'style'=>'color:#cf357c;'], '_YYYY-MM-DD_HHMMSS.pdf')
                ) .
                RCView::div(['class'=>'fs11 mt-1 ml-1', 'style'=>'color:#888;'], 'e.g., [last_name]_[first_name]_[dob]_record[record-name]')
            )
        );

        $html = "<form id='addEditSnapshot' method='post' action='".PAGE_FULL."'>$html</form>";

        print $html;
    }

    // Load the table of defined record snapshots on the setup page
    public function loadTable($displayInactive=false, $returnAsHtml=false)
    {
        $Proj = new Project(PROJECT_ID);
        $ec = new Econsent();
        $eConsentSurveys = $ec->getAllEconsents(PROJECT_ID, false);

        // Create array of survey_ids=>consent_ids
        $surveyIdsConsentIds = [];
        foreach ($eConsentSurveys as $attr) {
            if (!isset($surveyIdsConsentIds[$attr['survey_id']])) $surveyIdsConsentIds[$attr['survey_id']] = [];
            $surveyIdsConsentIds[$attr['survey_id']] = $eConsentSurveys[$attr['consent_id']];
        }

        // Load any record snapshot settings
        $pdfSnapshots = $this->getSnapshots(PROJECT_ID);

        $rows = [];

        // Add logic-based snapshots first
        $surveyTriggeredSnapshots = [];
        foreach ($pdfSnapshots as $row)
        {
            if ($row['trigger_surveycomplete_survey_id'] != '') {
                $surveyTriggeredSnapshots[] = $row;
                continue;
            }

            // Hide inactive versions?
            if (!$displayInactive && $row['active'] == '0') continue;

            $snapshot_id = $row['snapshot_id'];

            $action_icons = "";
            if ($row['active']) {
                $action_icons =
                    RCView::div(['class'=>'nowrap'],
                        RCView::button(['class' => 'btn btn-light btn-sm', 'data-bs-toggle'=>'tooltip', 'data-bs-original-title'=>RCView::tt_attr('econsent_172'), 'onclick' => "openSetupDialog($snapshot_id);"],
                            RCView::fa('fa-solid fa-pencil')
                        ) .
                        RCView::button(['class' => 'btn btn-light btn-sm mr-3', 'data-bs-toggle'=>'tooltip', 'data-bs-original-title'=>RCView::tt_attr('econsent_169'), 'onclick' => "copySnapshotConfirm($snapshot_id);"],
                            RCView::fa('fa-solid fa-copy')
                        )
                    );
            }
            if ($row['active']) {
                // Active
                $active = RCView::div(['class'=>'form-check form-switch fs18 ml-1'], RCView::checkbox(['class'=>'form-check-input', 'onclick'=>"toggleEnableSnapshot(this,$snapshot_id);", 'checked'=>'checked']) . RCView::label(['class'=>'form-check-label'], ""));
                $inactive_class = '';
            } else {
                // Inactive
                $active = RCView::div(['class'=>'form-check form-switch fs18 ml-1'], RCView::checkbox(['class'=>'form-check-input', 'onclick'=>"toggleEnableSnapshot(this,$snapshot_id);"]) . RCView::label(['class'=>'form-check-label'], ""));
                $inactive_class = 'opacity75 text-secondary';
            }
            $save_location = "";
            if (($row['pdf_save_to_file_repository']??'0') == '1') {
                $save_location .= RCView::div(['class' => 'nowrap fs11 mb-1 '.$inactive_class], RCView::fa('fas fa-folder-open fs14 mr-1') . RCView::tt('app_04'));
            }
            if (($row['pdf_save_to_field']??'') != '') {
                $save_location .= RCView::div(['class' => 'nowrap fs11 mb-1', 'title' => $row['pdf_save_to_field']],
                    RCView::i(['class' => 'fa-solid fa-arrow-right-to-bracket fs14', 'style' => 'margin-right:6px;'], '') . RCView::tt('econsent_20') . " " .
                    RCView::code(['style'=>'font-size:100%;'],
                        ($Proj->longitudinal && isinteger($row['pdf_save_to_event_id']) ? "[".$Proj->getUniqueEventNames($row['pdf_save_to_event_id'])."]" : "") .
                        "[".$row['pdf_save_to_field']."]"
                    )
                );
            }

            $logic = trim($row['trigger_logic']);
            if (mb_strlen($logic) > 35) $logic = trim(substr($logic, 0, 33))."...";
            $trigger = RCView::div(['class' => 'nowrap'], RCView::tt('econsent_17') . " " . RCView::code([], $logic));
            $triggerType = RCView::div(['class' => 'mr-3'],
                                RCView::fa('fa-solid fa-code fs20 text-tertiary') . RCView::div(['class' => 'fs11 nowrap'], RCView::tt('econsent_41'))
                            );
            $snapshotSize = RCView::div(['class' => 'nowrap fs11 mb-1 '.$inactive_class],
                ($row['selected_forms_events'] != '' && substr_count($row['selected_forms_events'], ',') === 0
                    ? RCView::fa('fa-regular fa-file fs14 mr-1') . RCView::tt('econsent_31')
                    : ($row['selected_forms_events'] == ''
                        ? RCView::fa('fa-solid fa-copy fs14 mr-1') . RCView::tt('econsent_04')
                        : RCView::fa('fa-regular fa-copy fs14 mr-1') . RCView::tt('econsent_03')
                    )
                )
            );
            // Add row
            $rows[] = [$active, $action_icons, RCView::div(['class'=>'fs11 wrap lineheight11'], $row['name']), $triggerType, $trigger, $snapshotSize, $save_location, RCView::div(['class'=>'text-secondary mr-2'], $snapshot_id)];
        }

        // Loop through all survey-completion-triggered snapshots
        foreach ($surveyTriggeredSnapshots as $row)
        {
            $survey_id = $row['trigger_surveycomplete_survey_id'];

            $snapshot_id = $row['snapshot_id'] ?? 0;
            $consent_id = $row['consent_id'] ?? null;
            $consentActive = $surveyIdsConsentIds[$survey_id]['active'] ?? 0;

            // Don't display surveys that don't have snapshots
            if ($snapshot_id == 0) continue;

            $rowActive = (($consent_id != null && $consentActive) || ($consent_id == null && $row['active']));

            // Hide inactive versions?
            if (!$displayInactive && !$rowActive) continue;

            // Common attributes
            if ($consent_id !== null) {
                $action_icons = RCView::div(['class' => 'text-secondary fs11 nowrap'], RCView::fa('fa-solid fa-lock mr-1').RCView::tt('econsent_21'));
            } elseif ($snapshot_id > 0) {
                // Edit settings for survey
                $action_icons =
                    RCView::div(['class'=>'nowrap'],
                        RCView::button(['class' => 'btn btn-light btn-sm', 'data-bs-toggle'=>'tooltip', 'data-bs-original-title'=>RCView::tt_attr('econsent_172'), 'onclick' => "openSetupDialog($snapshot_id);"],
                            RCView::fa('fa-solid fa-pencil')
                        ) .
                        RCView::button(['class' => 'btn btn-light btn-sm mr-3', 'data-bs-toggle'=>'tooltip', 'data-bs-original-title'=>RCView::tt_attr('econsent_169'), 'onclick' => "copySnapshotConfirm($snapshot_id);"],
                            RCView::fa('fa-solid fa-copy')
                        )
                    );
            }
            $disabledSwitch = $consent_id == null ? "" : "disabled";
            $uncheckedSwitch = $rowActive ? "checked" : "";
            $active = RCView::div(['class'=>'form-check form-switch fs18 ml-1'], RCView::checkbox(['class'=>'form-check-input', 'onclick'=>"toggleEnableSnapshot(this,$snapshot_id);", $uncheckedSwitch=>$uncheckedSwitch, $disabledSwitch=>$disabledSwitch]) . RCView::label(['class'=>'form-check-label'], ""));
            if ($row['active']) {
                // Active
                $inactive_class = '';
                // Where to save snapshot
                $save_location = "";
                if (($row['pdf_save_to_file_repository']??'0') == '1') {
                    $save_location .= RCView::div(['class' => 'nowrap fs11 mb-1 '.$inactive_class], RCView::fa('fas fa-folder-open fs14 mr-1') . RCView::tt('app_04'));
                }
                if (($row['pdf_save_to_field']??'') != '') {
                    $save_location .= RCView::div(['class' => 'nowrap fs11 mb-1', 'title' => $row['pdf_save_to_field']],
                        RCView::i(['class' => 'fa-solid fa-arrow-right-to-bracket fs14', 'style' => 'margin-right:6px;'], '') . RCView::tt('econsent_20') . " " .
                        RCView::code(['style'=>'font-size:100%;'],
                            ($Proj->longitudinal && isinteger($row['pdf_save_to_event_id']) ? "[".$Proj->getUniqueEventNames($row['pdf_save_to_event_id'])."]" : "") .
                            "[".$row['pdf_save_to_field']."]"
                        )
                    );
                }
                $snapshotSize = RCView::div(['class' => 'nowrap fs11 mb-1 '.$inactive_class],
                    ((($row['selected_forms_events'] != '' && substr_count($row['selected_forms_events'], ',') === 0) || $consent_id !== null)
                        ? RCView::fa('fa-regular fa-file fs14 mr-1') . RCView::tt('econsent_31')
                        : ($row['selected_forms_events'] == ''
                            ? RCView::fa('fa-solid fa-copy fs14 mr-1') . RCView::tt('econsent_04')
                            : RCView::fa('fa-regular fa-copy fs14 mr-1') . RCView::tt('econsent_03')
                        )
                    )
                );
            } else {
                // Inactive
                $inactive_class = 'opacity75 text-secondary';
                $save_location = '';
                $snapshotSize = '';
            }
            $title = RCView::div(['class' => 'wrap lineheight12'],
                RCView::span(['class'=>'mr-1 fs13'], RCView::tt('econsent_13') . " \"" . RCView::span(['class'=>'boldish text-dangerrc'], RCView::escape($Proj->surveys[$survey_id]['title'])) . "\" ").
                RCView::span(['class'=>'text-secondary fs12'], "({$Proj->surveys[$survey_id]['form_name']})")
            );
            $triggerType =  RCView::div(['class' => 'mr-3'],
                                RCView::fa('fa-solid fa-file-circle-check fs20 text-tertiary') . RCView::div(['class' => 'mt-1 lineheight10 fs11 wrap'], RCView::tt('econsent_33'))
                            );

            // Add row
            $rows[] = [$active, $action_icons, RCView::div(['class'=>'fs11 wrap lineheight11'], $row['name']), $triggerType, $title, $snapshotSize, $save_location, RCView::div(['class'=>'text-secondary mr-2'], $snapshot_id)];
        }

        // Return HTML
        if ($returnAsHtml) {
            return $rows;
        } else {
            // Return JSON
            return json_encode_rc(['data' => $rows]);
        }
    }

    // Return count of all pdf snapshots, excluding e-Consent governed snapshots
    public function numSnapshotsEnabled($project_id)
    {
        $sql = "select count(*) from redcap_pdf_snapshots where project_id = ? and active = 1 and isnull(consent_id)";
        $q = db_query($sql, $project_id);
        return db_result($q);
    }

    // Return array of all record snapshots for a project
    public function getSnapshots($project_id, $activeOnly=false, $singleSurveySnapshotsOnly=false, $snapshot_id=null)
    {
        $Proj = new Project($project_id);
        $longiMigrationFix = [];
        $longiMigrationFixConsentId = [];
        $event_replace = ['[firstname_event_id-replace][', '[lastname_event_id-replace][', '[dob_event_id-replace]['];
        // Query to get snapshots for project
        $sql = "select * from redcap_pdf_snapshots where project_id = ?";
        if ($activeOnly) $sql .= " and active = 1";
        if ($singleSurveySnapshotsOnly) $sql .= " and trigger_surveycomplete_survey_id is not null";
        if (isinteger($snapshot_id)) $sql .= " and snapshot_id = $snapshot_id";
        $sql .= " order by trigger_surveycomplete_survey_id, active desc, abs(name), name";
        $q = db_query($sql, $project_id);
        $rows = [];
        while ($row = db_fetch_assoc($q)) {
            // Make sure the survey instrument hasn't been deleted/orphaned
            if ($row['trigger_surveycomplete_survey_id'] != '' && !isset($Proj->surveys[$row['trigger_surveycomplete_survey_id']])) {
                continue;
            }
            // If this is an e-consent snapshot and we're wanting active only, ensure it is active in th eeconsent table too
            if ($row['consent_id'] != null && $activeOnly) {
                $q2 = db_query("select 1 from redcap_econsent where active = 1 and consent_id = ?", $row['consent_id']);
                if (db_num_rows($q2) == 0) {
                    continue;
                }
            }
            // Add to array of snapshots
            $rows[$row['snapshot_id']] = $row;
            // Check for the need to replace placeholder event names via v14.4.0 upgrade
            if ($row['consent_id'] != null && strpos($row['custom_filename_prefix']??'', "-replace][") !== false) {
                $longiMigrationFix[$row['snapshot_id']] = $row['custom_filename_prefix'];
                $longiMigrationFixConsentId[$row['snapshot_id']] = $row['consent_id'];
            }
        }
        // Longitudinal migration check
        if (!empty($longiMigrationFix)) {
            $ec = new Econsent();
            $econsentSettings = $ec->getAllEconsents($project_id);
            foreach ($longiMigrationFix as $this_snapshot_id=>$custom_filename_prefix) {
                $this_consent_id = $longiMigrationFixConsentId[$this_snapshot_id];
                $these_consent_settings = $econsentSettings[$this_consent_id];
                foreach ($event_replace as $this_repl) {
                    $this_repl_col = str_replace(['-replace]', '['], '', $this_repl);
                    $this_event_id = $these_consent_settings[$this_repl_col];
                    $this_event_name = isinteger($this_event_id) ? "[".$Proj->getUniqueEventNames($this_event_id)."][" : "[";
                    $custom_filename_prefix = str_replace($this_repl, $this_event_name, $custom_filename_prefix);
                }
                // Update the table with the new value
                $sql = "update redcap_pdf_snapshots set custom_filename_prefix = ? where snapshot_id = ?";
                db_query($sql, [$custom_filename_prefix, $this_snapshot_id]);
            }
        }
        // Return single array if id is provided, rather than subarrays
        if ($snapshot_id != null) $rows = $rows[$snapshot_id];
        return $rows;
    }


    // Add row in redcap_pdf_snapshots for e-Consent
    public function addSnapshotEntryForEconsent($project_id, $consent_id, $survey_id, $pdf_save_to_field, $pdf_save_to_event_id, $custom_filename_prefix=null)
    {
        $Proj = new Project($project_id);
        $form = $Proj->surveys[$survey_id]['form_name'];
        $params = [$project_id, 1, $pdf_save_to_field, $pdf_save_to_event_id, ":".$form, $custom_filename_prefix, $survey_id];

        $sql = "select 1 from redcap_pdf_snapshots where consent_id = ?";
        $q = db_query($sql, $consent_id);
        if (db_num_rows($q) == 0) {
            $sql = "insert into redcap_pdf_snapshots (active, pdf_save_to_file_repository, project_id, pdf_save_translated, pdf_save_to_field, 
                    pdf_save_to_event_id, selected_forms_events, custom_filename_prefix, trigger_surveycomplete_survey_id, consent_id) 
                    values (1, 1, ?, ?, ?, ?, ?, ?, ?, ".checkNull($consent_id).")";
        } else {
            $sql = "update redcap_pdf_snapshots 
                    set project_id = ?, pdf_save_translated = ?, pdf_save_to_field = ?, pdf_save_to_event_id = ?, selected_forms_events = ?, custom_filename_prefix = ?
                    where trigger_surveycomplete_survey_id = ? and consent_id ".($consent_id == null ? "is null" : "= ".checkNull($consent_id))."";
        }
        return db_query($sql, $params);
    }

    // Add row in redcap_pdf_snapshots for e-Consent
    public function removeSnapshotEntryForEconsent($project_id, $consent_id)
    {
        $sql = "delete from redcap_pdf_snapshots where project_id = ? and consent_id = ?";
        return db_query($sql, [$project_id, $consent_id]);
    }

    // Return boolean if there is an active eConsent OR non-eConsent snapshot enabled for a specific survey upon its completion
    public function triggerEnabledForSurveyCompletion($survey_id)
    {
        $sql = "select 1 from redcap_pdf_snapshots s
                left join redcap_econsent e on s.consent_id = e.consent_id
                where s.trigger_surveycomplete_survey_id = ? and s.active = 1
                and (e.active = 1 or e.active is null)
                limit 1";
        $q = db_query($sql, $survey_id);
        return (db_num_rows($q) > 0);
    }

    // Return array of attributes of all active e-Consent+ items for a given project and record that have not been triggered yet (i.e., not stored PDF in redcap_surveys_pdf_archive table)
    public function getUntriggeredLogicBasedTriggersForRecord($project_id, $record)
    {
        $sql = "select e.* from redcap_pdf_snapshots e
                left join redcap_pdf_snapshots_triggered a on e.snapshot_id = a.snapshot_id and a.record = ?
                where e.project_id = ? and e.trigger_logic is not null and e.consent_id is null and e.active = 1 and a.record is null";
        $q = db_query($sql, [$record, $project_id]);
        $rows = [];
        while ($row = db_fetch_assoc($q)) {
            $rows[$row['snapshot_id']] = $row;
        }
        return $rows;
    }

    // Check if we should trigger any logic-based snapshots if they are active AND logic is true AND they have not been triggered for this record yet.
    // If not triggered, then trigger them to save the specified PDF. Return count of snapshots saved.
    public function checkLogicBasedTrigger($project_id, $record, $form, $event_id, $instance)
    {
        $snapshotsToFieldTriggered = $snapshotsToRepoTriggered = 0;

        // Get all active untriggered logic-based snapshots for this record
        $pdfSnapshots = $this->getUntriggeredLogicBasedTriggersForRecord($project_id, $record);

        // Project attributes
        $Proj = new Project($project_id);
        $isRepeatingFormOrEvent = $Proj->isRepeatingFormOrEvent($event_id, $form);
        $isRepeatingForm = $Proj->isRepeatingForm($event_id, $form);

        // Loop through all untriggered e-Consent+ items for this record. Trigger any if logic is true.
        foreach ($pdfSnapshots as $snapshot_id=>$attr)
        {
            // Attributes
            $saveToField = $attr['pdf_save_to_field'] ?? false;
            $saveToFieldEventId = $attr['pdf_save_to_event_id'] ?? false;
            $saveToFieldTranslated = $attr['pdf_save_translated'] ?? 0;
            $saveToFileRepository = $attr['pdf_save_to_file_repository'] ?? 0;

            // If not been triggered yet, then evaluate logic
            $logicTrue = REDCap::evaluateLogic($attr['trigger_logic'], $project_id, $record, $event_id, ($isRepeatingFormOrEvent ? $instance : 1), ($isRepeatingForm ? $form : ""), $form);
            if (!$logicTrue) continue;

            // Save snapshot to File Repo and/or field
            $saveToRepoSuccess = $saveToFieldSuccess = false;
            if ($saveToFileRepository) {
                $saveToRepoSuccess = $this->saveSnapshotToFileRepository($project_id, $record, $event_id, $form, $instance, null, null, $saveToFieldTranslated, $attr['selected_forms_events'], $attr['pdf_compact'], false, $snapshot_id);
            }
            if ($saveToField) {
                $saveToFieldSuccess = $this->saveSnapshotToField($project_id, $record, $event_id, $form, $instance, null, $saveToField, $saveToFieldEventId, $saveToFieldTranslated, $attr['selected_forms_events'], $attr['pdf_compact'], $snapshot_id);
            }
            // Add record to table of triggered records
            if ($saveToRepoSuccess || $saveToFieldSuccess) {
                $this->markRecordAsTriggered($snapshot_id, $record);
                if ($saveToRepoSuccess) {
                    $snapshotsToRepoTriggered++;
                } else {
                    $snapshotsToFieldTriggered++;
                }
            }
        }

        // Call the method recursively in case saving a PDF snapshot to a field then triggers another snapshot to be saved
        $loops = 0;
        $maxLoops = 50; // Prevent infinite loop
        $args = func_get_args();
        while ($snapshotsToFieldTriggered > 0 && $loops < $maxLoops) {
            list ($snapshotsToRepoTriggered, $snapshotsToFieldTriggered) = call_user_func_array(array($this, __METHOD__), $args);
            $loops++;
        }

        // Return count of snapshots triggered
        return [$snapshotsToRepoTriggered, $snapshotsToFieldTriggered];
    }

    // Check if there exists a PDF Snapshot triggered by survey completion (eConsent or not), and if so, save it to a Field and/or File Repository
    public function checkSurveyCompletionTrigger($project_id, $record, $event_id, $form, $instance)
    {
        if ($record == null) return;
        $snapshotsToFieldTriggered = $snapshotsToRepoTriggered = 0;
        
        $Proj = new Project($project_id);
        $survey_id = $Proj->forms[$form]['survey_id'] ?? null;

        // Store a completed survey response as a PDF in the File Repository
        if ($this->triggerEnabledForSurveyCompletion($survey_id))
        {
            // Get attributes of all snapshot triggers
            $pdfSnapshots = $this->getSnapshots($project_id, true, true);

            // Loop through all triggers
            foreach ($pdfSnapshots as $snapshot_id=>$attr)
            {
                // Skip any snapshot triggers belonging to other surveys
                if ($survey_id != $attr['trigger_surveycomplete_survey_id']) continue;

                // Basic settings
                $consent_id = $attr['consent_id'];
                $consent_form_id = null;

                // saveToField settings
                $saveToField = $attr['pdf_save_to_field'] ?? false;
                $saveToFieldEventId = $attr['pdf_save_to_event_id'] ?? false;
                $saveToFieldTranslated = $attr['pdf_save_translated'] ?? 0;

                if ($consent_id != null) {
                    // e-Consent Snapshot Trigger
                    $dag_id = Records::getRecordGroupId($project_id, $record);
                    $context = Context::Builder()
                        ->project_id($project_id)
                        ->event_id($event_id)
                        ->instrument($form)
                        ->instance($instance)
                        ->record($record)
                        ->is_survey()
                        ->survey_id($survey_id);
                    $context = $context->Build();
                    $lang_id = MultiLanguage::getCurrentLanguage($context);
                    $eConsentSettings = Econsent::getEconsentSurveySettings($survey_id, $dag_id, $lang_id);
                    // Set the settings needed
                    $consent_id = $eConsentSettings['consent_id'] ?? null;
                    $consent_form_id = $eConsentSettings['consent_form_id'] ?? null;
                    $saveToFileRepository = 1; // always true for eConsent
                    $saveToFieldTranslated = 1; // always true for eConsent
                } else {
                    // Non e-Consent Snapshot Trigger
                    $saveToFileRepository = $attr['pdf_save_to_file_repository'] ?? 0;
                }

                // Save snapshot to File Repo and/or field
                $saveToRepoSuccess = $saveToFieldSuccess = false;
                if ($saveToFileRepository) {
                    $saveToRepoSuccess = $this->saveSnapshotToFileRepository($project_id, $record, $event_id, $form, $instance, $consent_id, $consent_form_id, $saveToFieldTranslated, $attr['selected_forms_events'], $attr['pdf_compact'], true, $snapshot_id);
                }
                if ($saveToField) {
                    $saveToFieldSuccess = $this->saveSnapshotToField($project_id, $record, $event_id, $form, $instance, $consent_id, $saveToField, $saveToFieldEventId, $saveToFieldTranslated, $attr['selected_forms_events'], $attr['pdf_compact'], $snapshot_id);
                }
                // Add record to table of triggered records
                if ($saveToRepoSuccess || $saveToFieldSuccess) {
                    $this->markRecordAsTriggered($snapshot_id, $record);
                    if ($saveToRepoSuccess) {
                        $snapshotsToRepoTriggered++;
                    } else {
                        $snapshotsToFieldTriggered++;
                    }
                }
            }
        }

        // Call the method recursively in case saving a PDF snapshot to a field then triggers another snapshot to be saved
//        $loops = 0;
//        $maxLoops = 50; // Prevent infinite loop
//        $args = func_get_args();
//        while ($snapshotsToFieldTriggered > 0 && $loops < $maxLoops) {
//            list ($snapshotsToRepoTriggered, $snapshotsToFieldTriggered) = call_user_func_array(array($this, __METHOD__), $args);
//            $loops++;
//        }

        // Return count of snapshots triggered
        return [$snapshotsToRepoTriggered, $snapshotsToFieldTriggered];
    }

    // Store a completed survey response as a PDF in the File Repository. Return boolean on whether successful.
    // $selected_forms_events=null assumes storing current event/form/instance, while $selected_forms_events='ALL' stores whole record. Other values will be subset of all forms/events.
    public function saveSnapshotToFileRepository($project_id, $record, $event_id, $form, $instance=1, $consent_id=null, $consent_form_id=null, $translateToSavePdf=0, $selected_forms_events=null,
                                                 $compactPDF=true, $surveyCompletionTrigger=false, $snapshot_id=null)
    {
        global $pdf_econsent_system_enabled, $pdf_econsent_system_ip;

        $Proj = new Project($project_id);
        $survey_id = $Proj->forms[$form]['survey_id'] ?? null;
        if ($instance == null) $instance = 1;
        if ($event_id == null) $event_id = $Proj->firstEventId;
        if ($form == null) $form = $Proj->firstForm;

        // Is Multilanguage active?
        $context = Context::Builder()
            ->project_id($project_id)
            ->event_id($event_id)
            ->instrument($form)
            ->instance($instance)
            ->record($record)
            ->is_pdf()
            ->user_id(defined('USERID') ? USERID : null)
            ->Build();
        $lang_id = MultiLanguage::getCurrentLanguage($context);
        $translatePdf = ($translateToSavePdf && !empty($lang_id));
        if ($translatePdf) {
            // Signal that PDF should be translated
            $_GET[MultiLanguage::LANG_GET_NAME] = $lang_id;
            // $pdf_custom_header_text = MultiLanguage::getPDFCustomHeaderTextTranslation($context);
        }
        else {
            // $pdf_custom_header_text = $Proj->project['pdf_custom_header_text'];
            $lang_id = MultiLanguage::getDefaultLanguage($context);
            // Signal what language should be used for the PDF
            if ($lang_id != null) {
                $_GET[MultiLanguage::LANG_GET_NAME] = $lang_id;
            }
        }
        // Update context with language
        $context = Context::Builder($context)->lang_id($lang_id)->Build();

        // For eConsent, get eConsent Options data
        $nameDobText = $versionText = $typeText = "";
        $econsentEnabledForSurvey = ($pdf_econsent_system_enabled && Econsent::econsentEnabledForSurvey($survey_id) && $consent_id != null);

        if ($econsentEnabledForSurvey) {
            $dag_id = Records::getRecordGroupId($project_id, $record);
            list ($nameDobText, $versionText, $typeText) = Econsent::getEconsentOptionsData($project_id, $record, $form, $dag_id, $lang_id);
            $versionTypeTextArray = array();
            if ($nameDobText != '')	$versionTypeTextArray[] = $nameDobText;
            if ($versionText != '')	$versionTypeTextArray[] = (!$translatePdf ? RCView::getLangStringByKey("data_entry_428") : MultiLanguage::getUITranslation($context, "data_entry_428"))." ".$versionText; //= Version:
            if ($typeText != '') 	$versionTypeTextArray[] = $typeText; //= Type:
        }

        // Set the PDF snapshot's filename
        $snapshotPrefix = $this->getSnapshots($project_id, false, false, $snapshot_id)['custom_filename_prefix'] ?? "";
        $snapshotPrefixPiped = Piping::replaceVariablesInLabel($snapshotPrefix, $record, $event_id, $instance, [], false, $project_id, false, ($Proj->isRepeatingForm($event_id, $form) ? $form : ""), 1, false, false, $form);
        $snapshotPrefixCleaned = preg_replace('/_+/', '_', ltrim(rtrim(str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z-_]/", "", $snapshotPrefixPiped))),"_"),"_"));
        $pdf_filename_base = ltrim($snapshotPrefixCleaned . "_" . date('Y-m-d_His') . ".pdf", "_");
        $pdf_filename = APP_PATH_TEMP . $pdf_filename_base;

        $pdfInstance = $econsentEnabledForSurvey && $Proj->isRepeatingFormOrEvent($event_id, $form) ? $instance : null;

        // Obtain the compact PDF of the response
        if ($consent_id == null && empty($selected_forms_events)) {
            // Store whole record
            $pdf_contents = REDCap::getPDF($record, null, null, false, $pdfInstance, $compactPDF, "", "", false, true, false, ($Proj->longitudinal ? $Proj->eventsForms : []), true);
        } else {
            // Store selected forms/events
            $selected_forms_events_array = $Proj->convertSelectedFormsEventsFromBackendAsArray($selected_forms_events, $event_id);
            // Is this a single event/form/instance? If so, reconfigure to a simpler format (especially if a repeating instrument, this will limit to only the current instance)
            $pdfEventId = $pdfInstrument = null;
            if ($consent_id != null || $selected_forms_events_array === [$event_id=>[$form]]) {
                $pdfEventId = $event_id;
                $pdfInstrument = $form;
                $pdfInstance = $instance;
                $selected_forms_events_array = null;
            }
            $pdf_contents = REDCap::getPDF($record, $pdfInstrument, $pdfEventId, false, $pdfInstance, $compactPDF, "", "", false, true, false, $selected_forms_events_array, true);
        }
        $contains_completed_consent = PDF::$contains_completed_consent ? 1 : 0;

        // Temporarily store file in temp
        file_put_contents($pdf_filename, $pdf_contents);
        // Add PDF to edocs_metadata table		
        $pdfFile = array('name'=>$pdf_filename_base, 'type'=>'application/pdf', 'size'=>filesize($pdf_filename), 'tmp_name'=>$pdf_filename);
        $pdf_edoc_id = Files::uploadFile($pdfFile);
        // Remove the original temp file
        if (file_exists($pdf_filename)) unlink($pdf_filename);
        if ($pdf_edoc_id == 0) return false;

        // Only capture IP address when completing an e-Consent survey (not for other snapshots)
        $ip = ($pdf_econsent_system_ip && $econsentEnabledForSurvey) ? System::clientIpAddress() : "";

        // Set survey_id as INT only if $surveyCompletionTrigger
        $survey_id_trigger = $surveyCompletionTrigger ? $survey_id : null;

        // Add values to redcap_surveys_pdf_archive table
        $sql = "insert into redcap_surveys_pdf_archive (doc_id, record, event_id, survey_id, instance, identifier, version, type, ip, consent_id, consent_form_id, snapshot_id, contains_completed_consent) values
				($pdf_edoc_id, '".db_escape($record)."', '".db_escape($event_id)."', ".checkNull($survey_id_trigger).", '".db_escape($instance)."', 
				".checkNull($nameDobText).", ".checkNull($versionText).", ".checkNull($typeText).", ".checkNull($ip).", ".checkNull($consent_id).", 
				".checkNull($consent_form_id).", ".checkNull($snapshot_id).", ".checkNull($contains_completed_consent).")";
        $q = db_query($sql);

        // VAULT: If project has External Storage enabled for e-Consent, then store file on that external server
        // IF the snapshot is an e-Consent governed snapshot (this is mandated and can never be disabled)
        // OR IF the snapshot is NOT an e-Consent governed snapshot BUT contains a completed e-Consent response inside it (unless this is disabled at the project-level on the Project Settings page).
        $storedFileExternal = null;
        if ($GLOBALS['pdf_econsent_filesystem_type'] != ''
            && ($econsentEnabledForSurvey || ($contains_completed_consent && $Proj->project['store_in_vault_snapshots_containing_completed_econsent']))
        ) {
            $storedFileExternal = Files::writeFilePdfAutoArchiverToExternalServer($pdf_filename_base, $pdf_contents);
        }

        // Set logging values
        $recordAttr = [];
        $recordAttr["record"] = $record;
        if ($econsentEnabledForSurvey) {
            // Get identifier (email and/or phone number)
            $participantEmailAddress = $Proj->getEmailInvitationValueByRecordEventForm($record, $event_id, $form);
            $participantPhone = $Proj->getParticipantPhoneByRecord($record, $event_id);
            if ($participantEmailAddress != '') {
                $recordAttr["identifier"] = $participantEmailAddress;
            } elseif ($participantPhone != '') {
                $recordAttr["identifier"] = $participantPhone;
            }
            // Add DAG and Language, if applicable
            if ($lang_id != '') $recordAttr["consent_form_language"] = $lang_id;
            if ($dag_id != '') $recordAttr["consent_form_data_access_group"] = $Proj->getUniqueGroupNames($dag_id);
            // Add consent form version, if applicable
            $eConsentSettings = Econsent::getEconsentSurveySettings($survey_id, $dag_id, $lang_id);
            $consent_form_id = $eConsentSettings['consent_form_id'] ?? null;
            if (isinteger($consent_form_id)) {
                $consentFormInfo = Econsent::getConsentFormsByConsentId($consent_id, $consent_form_id);
                if (isset($consentFormInfo['version'])) {
                    $recordAttr["consent_form_version"] = $consentFormInfo['version'];
                }
            }
        }
        if ($Proj->longitudinal) {
            $recordAttr["event"] = $Proj->getUniqueEventNames($event_id);
        }
        $recordAttr["instrument"] = $form;
        if ($Proj->isRepeatingFormOrEvent($event_id, $form)) {
            $recordAttr["instance"] = $instance;
        }
        if ($snapshot_id != null) $recordAttr["snapshot_id"] = $snapshot_id;
        $recordAttr["snapshot_file"] = $pdf_filename_base;

        // eConsent-specific Logging or non-eConsent?
        if ($econsentEnabledForSurvey) {
            // eConsent Logging
            $recordAttr["Electronic Signature Certification"] = str_replace(["\r\n","\r","\n","\t","  "], " ", RCView::tt('survey_1171',''));
            Logging::logEvent($sql, "redcap_pdf_snapshots", "MANAGE", $record, json_encode($recordAttr), "e-Consent Certification");
        } else {
            // Regular logging
            Logging::logEvent($sql, "redcap_pdf_snapshots", "MANAGE", $record, json_encode($recordAttr), "Save PDF Snapshot to File Repository");
        }

        // Return true on success
        return true;
    }

    // Save PDF of completed survey response to a field/event_id
    public function saveSnapshotToField($project_id, $record, $event_id, $form, $instance, $consent_id, $fieldToSavePdf, $eventIdToSavePdf, $translateToSavePdf=0, $selected_forms_events=null, $compactPDF=true, $snapshot_id=null)
    {
        $Proj = new Project($project_id);
        $survey_id = $Proj->forms[$form]['survey_id'] ?? null;
        if ($instance == null) $instance = 1;
        if ($event_id == null) $event_id = $Proj->firstEventId;
        if ($form == null) $form = $Proj->firstForm;

        $formToSavePdf = (isset($Proj->metadata[$fieldToSavePdf]) ? $Proj->metadata[$fieldToSavePdf]['form_name'] : "");
        if ($eventIdToSavePdf == '' || !$Proj->longitudinal) $eventIdToSavePdf = $event_id;
        // If the field is not set, then this feature is not enabled
        if ($fieldToSavePdf == '' || !isset($Proj->metadata[$fieldToSavePdf])) return false;

        // Is Multilanguage active?
        $context = Context::Builder()
            ->project_id($project_id)
            ->event_id($event_id)
            ->instrument($form)
            ->instance($instance)
            ->record($record)
            ->is_pdf()
            ->user_id(defined('USERID') ? USERID : null)
            ->Build();
        $lang_id = MultiLanguage::getCurrentLanguage($context);
        $translatePdf = ($translateToSavePdf && !empty($lang_id));
        if ($translatePdf) {
            // Signal that PDF should be translated
            $_GET[MultiLanguage::LANG_GET_NAME] = $lang_id;
            // $pdf_custom_header_text = MultiLanguage::getPDFCustomHeaderTextTranslation($context);
        }
        else {
            // $pdf_custom_header_text = $Proj->project['pdf_custom_header_text'];
            $lang_id = MultiLanguage::getDefaultLanguage($context);
            // Signal what language should be used for the PDF
            if ($lang_id != null) {
                $_GET[MultiLanguage::LANG_GET_NAME] = $lang_id;
            }
        }
        // Update context with language
        $context = Context::Builder($context)->lang_id($lang_id)->Build();

        $pdfInstance = isinteger($consent_id) && $Proj->isRepeatingFormOrEvent($event_id, $form) ? $instance : null;

        // Obtain the compact PDF of the response
        if ($consent_id == null && empty($selected_forms_events)) {
            // Store whole record
            $pdf_contents = REDCap::getPDF($record, null, null, false, $pdfInstance, $compactPDF, "", "", false, true, false, ($Proj->longitudinal ? $Proj->eventsForms : []), true);
        } else {
            // Store selected forms/events
            $selected_forms_events_array = $Proj->convertSelectedFormsEventsFromBackendAsArray($selected_forms_events, $event_id);
            // Is this a single event/form/instance? If so, reconfigure to a simpler format (especially if a repeating instrument, this will limit to only the current instance)
            $pdfEventId = $pdfInstrument = null;
            if ($consent_id != null || $selected_forms_events_array === [$event_id=>[$form]]) {
                $pdfEventId = $event_id;
                $pdfInstrument = $form;
                $pdfInstance = $instance;
                $selected_forms_events_array = null;
            }
            // Store partial record
            $pdf_contents = REDCap::getPDF($record, $pdfInstrument, $pdfEventId, false, $pdfInstance, $compactPDF, "", "", false, true, false, $selected_forms_events_array, true);
        }

        // Set the PDF snapshot's filename
        $snapshotPrefix = $this->getSnapshots($project_id, false, false, $snapshot_id)['custom_filename_prefix'] ?? "";
        $snapshotPrefixPiped = Piping::replaceVariablesInLabel($snapshotPrefix, $record, $event_id, $instance, [], false, $project_id, false, ($Proj->isRepeatingForm($event_id, $form) ? $form : ""), 1, false, false, $form);
        $snapshotPrefixCleaned = preg_replace('/_+/', '_', ltrim(rtrim(str_replace(" ", "_", trim(preg_replace("/[^0-9a-zA-Z-_]/", "", $snapshotPrefixPiped))),"_"),"_"));
        $pdf_filename_base = ltrim($snapshotPrefixCleaned . "_" . date('Y-m-d_His') . ".pdf", "_");

        // Generate the PDF
        $full_path_to_temp_file = APP_PATH_TEMP . date('YmdHis') . "_pid{$project_id}_savesurveypdf_" . substr(sha1(rand()), 0, 10) . ".pdf";
        file_put_contents($full_path_to_temp_file, $pdf_contents);
        // Store PDF in edocs
        $_FILE['type'] = "application/pdf";
        $_FILE['name'] = $pdf_filename_base;
        $_FILE['tmp_name'] = $full_path_to_temp_file;
        $_FILE['size'] = filesize($_FILE['tmp_name']);
        $doc_id = Files::uploadFile($_FILE);
        if (!is_numeric($doc_id)) return false; // unknown failure

        // Save the PDF to the record/event/field/instance
        $data = [0=>[$Proj->table_pk=>$record]];
        if ($Proj->longitudinal) {
            $data[0]['redcap_event_name'] = $Proj->getUniqueEventNames($eventIdToSavePdf);
        }
        if ($Proj->isRepeatingFormOrEvent($eventIdToSavePdf, $formToSavePdf)) {
            $data[0]['redcap_repeat_instrument'] = $Proj->isRepeatingForm($eventIdToSavePdf, $formToSavePdf) ? $formToSavePdf : "";
            $data[0]['redcap_repeat_instance'] = $instance;
        }
        $data[0][$fieldToSavePdf] = $doc_id;
        $saveDataParams = ['project_id'=>$project_id, 'dataFormat'=>'json', 'data'=>json_encode($data), 'skipFileUploadFields'=>false, 'bypassEconsentProtection'=>true];
        $response = Records::saveData($saveDataParams);

        // Set logging values
        $recordAttr = [];
        $recordAttr["field"] = $fieldToSavePdf . ($Proj->longitudinal ? " (".$Proj->getUniqueEventNames($eventIdToSavePdf).")" : "");
        $recordAttr["record"] = $record;
        if ($Proj->longitudinal) {
            $recordAttr["event"] = $Proj->getUniqueEventNames($event_id);
        }
        $recordAttr["instrument"] = $form;
        if ($Proj->isRepeatingFormOrEvent($event_id, $form)) {
            $recordAttr["instance"] = $instance;
        }
        if ($snapshot_id != null) $recordAttr["snapshot_id"] = $snapshot_id;
        $recordAttr['snapshot_file'] = $pdf_filename_base;
        $descrip = "Save PDF Snapshot to File Upload Field";
        Logging::logEvent("", "redcap_pdf_snapshots", "MANAGE", $record, json_encode($recordAttr), $descrip);

        // Return boolean on successfully saving the data value
        return empty($response['errors']);
    }

    // Return boolean based on whether any survey in the project has the PDF Auto-Archiver enabled
    public function hasSnapshotTriggersEnabled($project_id)
    {
        return !empty($this->getSnapshots($project_id, true));
    }

    // Add record to table of triggered records
    private function markRecordAsTriggered($snapshot_id, $record)
    {
        $sql = "replace into redcap_pdf_snapshots_triggered (snapshot_id, record) values (?, ?)";
        return db_query($sql, [$snapshot_id, $record]);
    }

    // Get all files stored by PDF Auto-Archiver. If provide $doc_id, then return just that file's attributes as an array.
    public static function getPdfSnapshotArchiveFiles(&$Proj, $group_id=null, $doc_id=null)
    {
        // Filter by DAG, if needed
        $dagsql = "";
        if (is_numeric($group_id)) {
            $dagsql = "and a.record in (" . prep_implode(Records::getRecordList($Proj->project_id, $group_id)) . ")";
        }
        // Get all events_ids for project
        $event_ids = array_keys($Proj->eventInfo);
        // Get all survey_ids for project
        $survey_ids = array_keys($Proj->surveys);
        if (empty($survey_ids)) $survey_ids = [''];
        // Get all consent_ids for project
        $consent_ids = array_keys(Econsent::getEconsentSettings($Proj->project_id));
        if (empty($consent_ids)) $consent_ids = [''];
        // Get all snapshot_ids for project
        $rss = new PdfSnapshot();
        $snapshot_ids = array_keys($rss->getSnapshots($Proj->project_id));
        if (empty($snapshot_ids)) $snapshot_ids = [''];
        // Query table
        $files = array();
        $sql = "select e.stored_date, e.doc_size, e.doc_name, a.*
				from redcap_surveys_pdf_archive a, redcap_edocs_metadata e
				where e.doc_id = a.doc_id and a.event_id in (".prep_implode($event_ids).")
				$dagsql and e.delete_date is null and e.project_id = {$Proj->project_id}
				and (
				    (a.survey_id in (" . prep_implode($survey_ids) . "))
				    or (a.consent_id in (" . prep_implode($consent_ids) . "))
				    or (a.snapshot_id in (" . prep_implode($snapshot_ids) . "))
                )";
        if (is_numeric($doc_id)) {
            $sql .= " and e.doc_id = $doc_id";
        }
        $sql .= " order by e.doc_id desc";
        $q = db_query($sql);
        while ($row = db_fetch_assoc($q)) {
            if ($doc_id !== null) return $row;
            else $files[] = $row;
        }
        return $files;
    }

    // Re-enable snapshot trigger
    public function reenable($snapshot_id)
    {
        if (!isinteger($snapshot_id)) exit('0');
        $sql = "update redcap_pdf_snapshots set active = 1 where snapshot_id = ?";
        if (db_query($sql, $snapshot_id)) {
            print RCView::tt('econsent_127');
            Logging::logEvent($sql, "redcap_record_snapshot", "MANAGE", PROJECT_ID, "snapshot_id = $snapshot_id", "Re-enable PDF Snapshot Trigger (snapshot_id = $snapshot_id)");
        } else {
            exit('0');
        }
    }

    // Disable snapshot trigger
    public function disable($snapshot_id)
    {
        if (!isinteger($snapshot_id)) exit('0');
        $sql = "update redcap_pdf_snapshots set active = 0 where snapshot_id = ?";
        if (db_query($sql, $snapshot_id)) {
            print RCView::tt('econsent_128');
            Logging::logEvent($sql, "redcap_record_snapshot", "MANAGE", PROJECT_ID, "snapshot_id = $snapshot_id", "Disable PDF Snapshot Trigger (snapshot_id = $snapshot_id)");
        } else {
            exit('0');
        }
    }

    // Copy snapshot trigger
    public function copy($snapshot_id)
    {
        if (!isinteger($snapshot_id)) exit('0');
        $cols = getTableColumns('redcap_pdf_snapshots');
        unset($cols['snapshot_id']);
        $colList = implode(", ", array_keys($cols));
        $sql = "insert into redcap_pdf_snapshots ($colList) select $colList from redcap_pdf_snapshots where snapshot_id = ?";
        if (db_query($sql, $snapshot_id)) {
            $new_snapshot_id = db_insert_id();
            print RCView::tt('econsent_171');
            Logging::logEvent($sql, "redcap_record_snapshot", "MANAGE", PROJECT_ID, "snapshot_id = $new_snapshot_id", "Copy PDF Snapshot Trigger (copy snapshot_id = $snapshot_id, new snapshot_id = $new_snapshot_id)");
        } else {
            exit('0');
        }
    }

    // Display dialog to manually trigger or re-trigger PDF Snapshots
    public function manualTriggerDialog($record, $event_id=null, $form=null, $instance=1)
    {
        global $lang;
        if ($record == null || !Records::recordBelongsToUsersDAG(PROJECT_ID, $record)) exit('0');
        $Proj = new Project();

        $snapshotAttr = $this->getSnapshots(PROJECT_ID, true);
        $snapshotsTriggered = $this->getSnapshotsTriggered(PROJECT_ID, $record);
        $triggers = $this->loadTable(false, true);

        // Build table
        $tbl_attr = array('cellspacing'=>2, 'cellpadding'=>2, 'class'=>'dataTable lineheight11');
        $td_attr = array('style'=>'border:1px solid #ccc;padding:5px 8px !important;');
        $td_center_attr = array('style'=>'border:1px solid #ccc;padding:5px 8px !important;text-align:center;');
        $td_attr_gray = array('style'=>'border:1px solid #ccc;padding:5px 8px !important;background-color:#ddd;');
        $td_center_attr_gray = array('style'=>'border:1px solid #ccc;padding:5px 8px !important;text-align:center;background-color:#ddd;');
        $th_attr = array('class'=>'header', 'style'=>'border:1px solid #ccc;');
        $cells = RCView::th($th_attr, $lang['econsent_152']) .
            RCView::th($th_attr, $lang['econsent_153']) .
            RCView::th($th_attr, $lang['docs_77']) .
            RCView::th($th_attr, $lang['econsent_42']) .
            RCView::th($th_attr, $lang['econsent_09']) .
            RCView::th($th_attr, $lang['econsent_142']) .
            RCView::th($th_attr, $lang['econsent_16']) .
            RCView::th($th_attr, $lang['econsent_150'])
        ;
        $header = RCView::thead(array(), RCView::tr(array(), $cells));
        $rows = '';
        // Loop through all consent forms
        foreach ($triggers as $trigger) {
            $rowDisabled = false;
            $cells = '';
            $snapshot_id_key = max(array_keys($trigger));
            $snapshot_id = (int)trim(strip_tags($trigger[$snapshot_id_key]));
            $alreadyTriggered = in_array($snapshot_id, $snapshotsTriggered);
            $attr = $snapshotAttr[$snapshot_id] ?? [];
            foreach ($trigger as $tkey=>$cell) {
                $this_td_attr = $td_attr;
                if ($tkey <= 1) $cell = strip_tags(br2nl($cell));
                if ($tkey <= 2) $this_td_attr = $td_center_attr;
                if ($tkey == 0 && $alreadyTriggered) {
                    $cell = RCView::img(array('src'=>'checkbox_checked.png'));
                } elseif ($tkey == 1) {
                    // Add trigger button in column 2
                    $saveSnapshotToField = $attr['pdf_save_to_field'] != '' ? 1 : 0;
                    $isEconsentTrigger = $attr['consent_id'] != '' ? 1 : 0;
                    $isSurveyCompletionTrigger = ($attr['trigger_surveycomplete_survey_id'] != '');
                    $isSurveyCompletionTriggerCorrectEvent = ($attr['trigger_surveycomplete_event_id'] == '' || $attr['trigger_surveycomplete_event_id'] == $event_id);
                    $isSurveyCompletionTriggerCorrectForm = (!$isSurveyCompletionTrigger || ($isSurveyCompletionTrigger && $attr['trigger_surveycomplete_survey_id'] == $Proj->forms[$form]['survey_id']));
                    // Set button defaults
                    $btnDisabled = "";
                    $btnClass = "btn-rcgreen";
                    $btnWord = $alreadyTriggered ? $lang['econsent_155'] : $lang['econsent_151'];
                    // Don't make button clickable it is a survey completion trigger that hasn't been triggered yet OR if we're not in the correct survey/event context for it to be triggered
                    $rowDisabled = ($isSurveyCompletionTrigger && (!$isSurveyCompletionTriggerCorrectForm || !$isSurveyCompletionTriggerCorrectEvent || !Survey::isResponseCompleted($attr['trigger_surveycomplete_survey_id'], $record, ($attr['trigger_surveycomplete_event_id'] == '' ? $Proj->firstEventId : $attr['trigger_surveycomplete_event_id']), $instance)));
                    if ($isSurveyCompletionTrigger && (!$isSurveyCompletionTriggerCorrectForm || !$isSurveyCompletionTriggerCorrectEvent || !Survey::isResponseCompleted($attr['trigger_surveycomplete_survey_id'], $record, ($attr['trigger_surveycomplete_event_id'] == '' ? $Proj->firstEventId : $attr['trigger_surveycomplete_event_id']), $instance))) {
                        $btnDisabled = "disabled";
                        $btnClass = "btn-defaultrc";
                    }
                    // Output cell
                    $eConsentText = (!$isEconsentTrigger ? '' : RCView::div(['class'=>'fs11 mt-2 text-primaryrc'], RCView::fa('fa-solid fa-lock mr-1').$lang['econsent_21']));
                    $cell = RCView::button(['class'=>"btn btn-xs fs13 mt-1 nowrap mx-3 $btnClass", $btnDisabled=>$btnDisabled, 'onclick'=>"triggerSinglePdfSnapshotPrompt($snapshot_id,$isEconsentTrigger,$saveSnapshotToField);"],
                                RCView::fa('fa-solid fa-camera mr-1').$btnWord
                            ) .
                            $eConsentText;
                }
                $cells .= RCView::td($this_td_attr, $cell);
            }
            $rows .= RCView::tr(array(), $cells);
        }
        // Output table for dialog
        print RCView::p(['class'=>'mt-0 mb-4', 'style'=>'max-width:1000px;'],
            RCView::tt('econsent_154')
        );
        print RCView::table($tbl_attr, $header . RCView::tbody(array(), $rows));
    }

    // Manually trigger or re-trigger PDF Snapshots. Return count of snapshots triggered.
    public function manualTriggerSave($snapshot_id, $record, $event_id=null, $form=null, $instance=1)
    {
        global $user_rights;
        if ($record == null || !isinteger($snapshot_id) || !Records::recordBelongsToUsersDAG(PROJECT_ID, $record)) return [0,0];
        // Validate snapshot_id
        $attr = $this->getSnapshots(PROJECT_ID, true)[$snapshot_id];
        if (empty($attr)) return [0,0];
        // Validate context
        $Proj = new Project();
        $isEconsentTrigger = $attr['consent_id'] != '';
        $isSurveyCompletionTrigger = ($attr['trigger_surveycomplete_survey_id'] != '');
        $isSurveyCompletionTriggerCorrectEvent = ($attr['trigger_surveycomplete_event_id'] == '' || $attr['trigger_surveycomplete_event_id'] == $event_id);
        $isSurveyCompletionTriggerCorrectForm = (!$isSurveyCompletionTrigger || ($isSurveyCompletionTrigger && $attr['trigger_surveycomplete_survey_id'] == $Proj->forms[$form]['survey_id']));
        if ($isSurveyCompletionTrigger && (!$isSurveyCompletionTriggerCorrectForm || !$isSurveyCompletionTriggerCorrectEvent || !Survey::isResponseCompleted($attr['trigger_surveycomplete_survey_id'], $record, ($attr['trigger_surveycomplete_event_id'] == '' ? $Proj->firstEventId : $attr['trigger_surveycomplete_event_id']), $instance))) {
            // Stop here if context is not correct or if a survey has not been completed yet for a survey-completion trigger
            return [0,0];
        }
        // Validate user rights (user must have view access to this form if this is a survey completion trigger)
        // Note to @taylorr4: Is this comment correct? It should say: "user must have edit access to this form"
        if ($isSurveyCompletionTrigger && !UserRights::hasDataViewingRights($user_rights['forms'][$form], "view-edit")) {
            return [0,0];
        }
        // Attributes
        $saveToField = $attr['pdf_save_to_field'] ?? false;
        $saveToFieldEventId = $attr['pdf_save_to_event_id'] ?? false;
        $saveToFieldTranslated = $attr['pdf_save_translated'] ?? 0;
        $saveToFileRepository = $attr['pdf_save_to_file_repository'] ?? 0;
        // Save snapshot to File Repo and/or field
        $snapshotsToFieldTriggered = $snapshotsToRepoTriggered = 0;
        $saveToRepoSuccess = $saveToFieldSuccess = false;
        if ($saveToFileRepository) {
            $saveToRepoSuccess = $this->saveSnapshotToFileRepository(PROJECT_ID, $record, $event_id, $form, $instance, null, null, $saveToFieldTranslated, $attr['selected_forms_events'], $attr['pdf_compact'], false, $snapshot_id);
        }
        if ($saveToField) {
            $saveToFieldSuccess = $this->saveSnapshotToField(PROJECT_ID, $record, $event_id, $form, $instance, null, $saveToField, $saveToFieldEventId, $saveToFieldTranslated, $attr['selected_forms_events'], $attr['pdf_compact'], $snapshot_id);
        }
        // Add record to table of triggered records
        if ($saveToRepoSuccess || $saveToFieldSuccess) {
            // Add e-Consent re-trigger logging
            if ($isEconsentTrigger) {
                // Set logging values
                $recordAttr = [];
                $recordAttr["record"] = $record;
                // Get identifier (email)
                $participantEmailAddress = $Proj->getEmailInvitationValueByRecordEventForm($record, $event_id, $form);
                if ($participantEmailAddress != '') {
                    $recordAttr["identifier"] = $participantEmailAddress;
                }
                if ($Proj->longitudinal) {
                    $recordAttr["event"] = $Proj->getUniqueEventNames($event_id);
                }
                $recordAttr["instrument"] = $form;
                if ($Proj->isRepeatingFormOrEvent($event_id, $form)) {
                    $recordAttr["instance"] = $instance;
                }
                if ($snapshot_id != null) $recordAttr["snapshot_id"] = $snapshot_id;
                $recordAttr["snapshot_file"] = $pdf_filename_base;
                $recordAttr["Electronic Signature Certification"] = str_replace(["\r\n","\r","\n","\t","  "], " ", RCView::tt('econsent_160',''));
                Logging::logEvent("", "redcap_pdf_snapshots", "MANAGE", $record, json_encode($recordAttr), "e-Consent PDF Snapshot Regeneration");
            }
            $this->markRecordAsTriggered($snapshot_id, $record);
            if ($saveToRepoSuccess) {
                $snapshotsToRepoTriggered++;
            } else {
                $snapshotsToFieldTriggered++;
            }
        }
        // Return count of snapshots triggered
        return [$snapshotsToRepoTriggered, $snapshotsToFieldTriggered];
    }

    // Return array of snapshot_ids where a specified record has already been triggered for the PDF Snapshot
    public function getSnapshotsTriggered($project_id, $record)
    {
        if ($record == null || !Records::recordBelongsToUsersDAG(PROJECT_ID, $record)) return false;
        $sql = "select s.snapshot_id from redcap_pdf_snapshots s, redcap_pdf_snapshots_triggered t 
                where t.snapshot_id = s.snapshot_id and s.project_id = ? and t.record = ?";
        $q = db_query($sql, [$project_id, $record]);
        $snapshot_ids = [];
        while ($row = db_fetch_assoc($q)) {
            $snapshot_ids[] = $row['snapshot_id'];
        }
        return $snapshot_ids;
    }

}