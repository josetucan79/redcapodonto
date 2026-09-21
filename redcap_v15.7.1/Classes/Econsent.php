<?php

use MultiLanguageManagement\MultiLanguage;
use REDcap\Context;

class Econsent
{
    // Render the initial eConsent setup page
    public function renderSetup()
    {
        global $Proj, $lang;
        // JS/CSS
        addLangToJS(['control_center_439', 'econsent_06', 'setup_87', 'econsent_10', 'econsent_15', 'econsent_08', 'global_53', 'survey_152', 'econsent_45', 'econsent_176', 'econsent_177',
                     'calendar_popup_11', 'econsent_18', 'econsent_37', 'survey_437', 'econsent_39','econsent_44','econsent_46','econsent_47', 'econsent_119', 'econsent_136',
                     'econsent_50', 'control_center_4878', 'econsent_60', 'control_center_4879', 'econsent_78', 'econsent_70', 'econsent_82', 'econsent_83', 'econsent_133',
                     'econsent_84', 'econsent_85', 'econsent_86', 'econsent_92', 'econsent_93', 'econsent_94', 'econsent_79', 'econsent_96', 'econsent_100', 'econsent_43']);
        print RCView::script("var numEconsentSignatureFields = ".Survey::numEconsentSignatureFields.";");
        loadJS('EconsentSetup.js');
        loadCSS('EconsentSetup.css');
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
        $this->renderTabs();
        // Instructions
        print RCView::p(['class' => 'mt-1 mb-4 pb-1', 'style' => 'max-width:1000px;'],
            RCView::tt('survey_1176')." ".RCView::tt('econsent_05') . " " .
            RCView::a(['href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"simpleDialog(null,null,'econsent_explain',900);fitDialog($('#econsent_explain'));"], RCView::tt('scheduling_78',''))
        );
        ?>
        <!-- Hidden div for explaining e-Consent -->
        <div id="econsent_explain" class="simpleDialog"" title="<?php echo js_escape2($lang['survey_1179']) ?>">
            <p style="margin-top:0;"><b><?php echo $lang['survey_1186'] ?></b><br><?php echo $lang['survey_1176'] ?> <b style="color:#C00000;"><?php echo $lang['survey_1211'] ?></b></p>
            <p><b><?php echo $lang['survey_1185'] ?></b><br><?php echo $lang['econsent_162'] ?></p>
            <p><b><?php echo $lang['survey_1187'] ?></b><br><?php echo $lang['survey_1188'] ?></p>
            <p><b><?php echo $lang['survey_1183'] ?></b><br><?php echo $lang['survey_1184'] ?></p>
            <p style="margin-bottom:0;"><b><?php echo $lang['econsent_163'] ?></b><br><?php echo $lang['econsent_164'] ?></p>
        </div>
        <?php
        // If no surveys are enabled in the project, display a notice that e-Consent cannot yet be used
        if (empty($Proj->surveys)) print RCView::p(['class' => 'mt-1 mb-5 alert alert-danger fs14', 'style' => 'max-width:900px;'], RCView::fa('fa-solid fa-circle-exclamation mr-1') . RCView::tt('econsent_40'));
        // Table placeholder
        print RCView::div(['id' => 'econsent-table-parent'], RCView::table(['id' => 'econsent-table'], ''));
    }

    // Render the tabs for the page
    public function renderTabs()
    {
        global $pdf_econsent_system_enabled;
        $get = isset($_GET['display_inactive']) && in_array($_GET['display_inactive'], ['0', '1']) ? "&display_inactive=".$_GET['display_inactive'] : "";
        $tabs = [];
        $tabs['Design/online_designer.php'] = RCView::span(['class'=>'text-secondary fs12'], RCView::fa('fa-solid fa-circle-chevron-left mr-1') . RCView::tt('econsent_07'));
        if ($pdf_econsent_system_enabled) $tabs['index.php?route=EconsentController:index'.$get] = RCIcon::OnlineDesignerEConsent("me-1") . RCView::tt('econsent_29');
        $tabs['index.php?route=PdfSnapshotController:index'] = RCIcon::OnlineDesignerPDFSnapshot("me-1") . RCView::tt('econsent_30');
        RCView::renderTabs($tabs);
    }

    // Save new version
    public function saveSetup($consent_id=null, $survey_id=null)
    {
        $Proj = new Project(PROJECT_ID);
        $colsEconsent = getTableColumns('redcap_econsent');
        if ($consent_id != null && !isinteger($consent_id)) exit('0');
        // Add posted values to array
        $colsEconsent['consent_id'] = $consent_id;
        $colsEconsent['project_id'] = PROJECT_ID;
        $colsEconsent['version'] = strip_tags($colsEconsent['version']??"");
        $colsEconsent['type_label'] = strip_tags($colsEconsent['type_label']??"");
        $colsEconsent['custom_econsent_label'] = strip_tags($colsEconsent['custom_econsent_label']??"");
        foreach ($_POST as $key=>$val) {
            // Add to consent settings array
            if (!array_key_exists($key, $colsEconsent)) continue;
            if ($key == 'allow_edit') {
                $val = ($val == 'on') ? 1 : 0;
            }
            $colsEconsent[$key] = $val;
        }
        // Begin transaction
        db_query("SET AUTOCOMMIT=0"); db_query("BEGIN");
        // Save settings
        unset($colsEconsent['consent_id']);
        unset($colsEconsent['consent_form_location_field']);
        $colsEconsent['active'] = 1;
        $sql_all = [];
        if ($consent_id == '') {
            $sql_all[] = $sql = "insert into redcap_econsent (".implode(", ", array_keys($colsEconsent)).") values (".prep_implode($colsEconsent, true, true).")";
            $q = db_query($sql);
            $consent_id = db_insert_id();
        } else {
            $updateSql = [];
            foreach ($colsEconsent as $col=>$val) {
                $val = ($val === null) ? "null" : "'".db_escape($val)."'";
                $updateSql[] = "$col = $val";
            }
            $updateSql = implode(", ", $updateSql);
            $sql_all[] = $sql = "update redcap_econsent set $updateSql where consent_id = $consent_id";
            $q = db_query($sql);
        }
        if (!$q) {
            // Roll back changes on error
            db_query("ROLLBACK"); db_query("SET AUTOCOMMIT=1");
            exit('0');
        }
        // Also save row in redcap_pdf_snapshots table
        $pdf_save_to_field = (isset($_POST['pdf_save_to_field']) && isset($Proj->metadata[$_POST['pdf_save_to_field']])) ? $_POST['pdf_save_to_field'] : null;
        $pdf_save_to_event_id = $_POST['pdf_save_to_event_id'] ?? null;
        if ($pdf_save_to_field == '') {
            $pdf_save_to_field = null;
            $pdf_save_to_event_id = null;
        }
        if ($pdf_save_to_event_id == '') $pdf_save_to_event_id = null;
        $rss = new PdfSnapshot();
        $q = $rss->addSnapshotEntryForEconsent($Proj->project_id, $consent_id, $survey_id, $pdf_save_to_field, $pdf_save_to_event_id, ($_POST['custom_filename_prefix']??null));
        if (!$q) {
            // Roll back changes on error
            db_query("ROLLBACK"); db_query("SET AUTOCOMMIT=1");
            exit('0');
        }
        // Logging
        Logging::logEvent(implode("; ", $sql_all), "redcap_econsent", "MANAGE", PROJECT_ID, "project_id = " . PROJECT_ID,
                ($consent_id == '' ? "Enable e-Consent for instrument \"{$Proj->surveys[$survey_id]['form_name']}\"" : "Modify e-Consent settings for instrument \"{$Proj->surveys[$survey_id]['form_name']}\""));
        // Commit changes
        db_query("COMMIT"); db_query("SET AUTOCOMMIT=1");
        // Return success message
        $title = strip_tags($Proj->surveys[$survey_id]['title']);
        $msg = RCView::tt_i(($consent_id == '' ? 'econsent_61' : 'econsent_62'), [$title]);
        exit($msg);
    }

    // Return consent_id of a stored consent form for a given record/event/survey/instance
    public static function getRecordCountConsented($consent_form_ids=[])
    {
        if (empty($consent_form_ids)) return 0;
        $sql = "select consent_form_id, count(*) as consent_count from redcap_surveys_pdf_archive 
                where consent_form_id in (".prep_implode($consent_form_ids).")
                group by consent_form_id";
        $q = db_query($sql);
        $rows = [];
        while ($row = db_fetch_assoc($q)) {
            $rows[$row['consent_form_id']] = $row['consent_count'];
        }
        return $rows;
    }

    // View list of all consent form versions for a survey
    public static function viewConsentFormVersions($consent_id, $survey_id)
    {
        global $lang;
        if (!isinteger($consent_id)) exit('0');
        $Proj = new Project();
        $forms = self::getConsentFormsByConsentId($consent_id, null, false, true);
        $textMaxLength = 50;
        // Build table
        $tbl_attr = array('cellspacing'=>2, 'cellpadding'=>2, 'class'=>'dataTable lineheight11');
        $td_attr = array('style'=>'border:1px solid #ccc;');
        $td_center_attr = array('style'=>'border:1px solid #ccc;text-align:center;');
        $th_attr = array('style'=>'border:1px solid #ccc;font-weight:bold;');
        $cells = RCView::th($th_attr, $lang['econsent_02']) .
                 RCView::th($th_attr, $lang['survey_1173']) .
                 RCView::th($th_attr, $lang['econsent_137']) .
                 RCView::th($th_attr, $lang['docs_1124']) .
                 RCView::th($th_attr, $lang['econsent_139']) .
                 RCView::th($th_attr, $lang['global_78']) .
                 RCView::th($th_attr, $lang['econsent_22']) .
                 RCView::th($th_attr, $lang['econsent_138']) .
                 RCView::th($th_attr, $lang['econsent_130'])
        ;
        $header = RCView::thead(array(), RCView::tr(array(), $cells));
        $rows = '';
        // Get count of records that were consented with each consent form
        $consent_form_ids = [];
        foreach ($forms as $form) {
            $consent_form_ids[] = $form['consent_form_id'];
        }
        $recordCountConsentedByForm = self::getRecordCountConsented($consent_form_ids);
        // Loop through all consent forms
        foreach ($forms as $form) {
            $consent_form_id = $form['consent_form_id'];
            if (isinteger($form['consent_form_pdf_doc_id'])) {
                $consentFormText = RCView::a(['style'=>'text-decoration:underline;', 'class'=>'text-dangerrc nowrap', 'href'=>'javascript:;', 'onclick'=>"viewConsentForm($consent_id,$consent_form_id);"], RCView::fa('far fa-file-pdf fs14 mr-1').truncateTextMiddle(Files::getEdocName($form['consent_form_pdf_doc_id'])));
            } else {
                $consentFormText = '"'.strip_tags(br2nl($form['consent_form_richtext'])).'"';
                if (mb_strlen($consentFormText) > $textMaxLength) {
                    $consentFormDlgId = 'form-richtext-'.$consent_form_id;
                    $consentFormText =  RCView::span([], mb_substr($consentFormText, 0, $textMaxLength-2)."...\" ") .
                                        RCView::a(['style'=>'text-decoration:underline;', 'class'=>'fs11 nowrap', 'href'=>'javascript:;', 'onclick'=>"simpleDialog(null,'".RCView::tt_js('econsent_136')."','$consentFormDlgId',700);"], RCView::tt('econsent_135')) .
                                        RCView::div(['id'=>$consentFormDlgId, 'class'=>'simpleDialog'], filter_tags($form['consent_form_richtext']));
                }
            }
            $setInactiveBtn = $form['consent_form_active'] ? RCView::button(['class'=>'btn btn-outline-danger btn-xs fs11 nowrap', 'onclick'=>"simpleDialog('".RCView::tt_js('econsent_131')."','".RCView::tt_js('econsent_130')."','removeConsentFormDialog',500,null,'".RCView::tt_attr('global_53')."','removeConsentForm($consent_id,$consent_form_id,$survey_id)','".RCView::tt_js('econsent_130')."');return false;"], RCView::fa('fas fa-ban mr-1').RCView::tt('econsent_96')) : "";
            $uploaderText = "";
            if ($form['uploader'] != '') {
                $userInfo = User::getUserInfoByUiid($form['uploader']);
                $uploaderText = $userInfo['username'].($userInfo['user_firstname'] != '' || $userInfo['user_lastname'] != '' ? " (".trim("{$userInfo['user_firstname']} {$userInfo['user_lastname']}").")" : "");
            }
            $cells = RCView::td($td_center_attr, $form['consent_form_active'] ? RCView::fa('fa-solid fa-check') : "") .
                     RCView::td($td_attr, $form['version']) .
                     RCView::td($td_attr, DateTimeRC::format_user_datetime($form['creation_time'], 'Y-M-D_24')) .
                     RCView::td($td_attr, $uploaderText) .
                     RCView::td($td_center_attr, $recordCountConsentedByForm[$consent_form_id] ?? RCView::span(['class'=>'text-tertiary'], "0")) .
                     RCView::td($td_attr, $form['consent_form_filter_dag_id'] == '' ? "" : $Proj->getGroups($form['consent_form_filter_dag_id'])) .
                     RCView::td($td_attr, $form['consent_form_filter_lang_id']) .
                     RCView::td($td_attr, $consentFormText) .
                     RCView::td($td_attr, $setInactiveBtn);
            $rows .= RCView::tr(array(), $cells);
        }
        // Output table for dialog
        print RCView::p(['class'=>'mt-0 mb-4'], RCView::tt('econsent_134')." \"".RCView::span(['class'=>'boldish text-dangerrc'], strip_tags($Proj->surveys[$survey_id]['title']))."\"".RCView::tt('period'));
        print RCView::table($tbl_attr, $header . RCView::tbody(array(), $rows));
    }

    // Delete consent form in table (because wrong file type)
    public function deleteConsentForm($consent_id, $consent_form_id)
    {
        if (!isinteger($consent_id) || !isinteger($consent_form_id)) exit('0');
        $sql = "delete from redcap_econsent_forms where consent_id = $consent_id and consent_form_id = $consent_form_id";
        print (db_query($sql) ? '1' : '0');
    }

    // Upload PDF consent form
    public function uploadConsentForm($consent_id, $consent_form_id)
    {
        global $lang;
        if (!isinteger($consent_id) || !isinteger($consent_form_id)) exit('0');
        // check to see if a file was uploaded
        if (!(isset($_FILES['file']) && is_array($_FILES['file'])) || $_FILES['file']['error'] != 0) {
            exit('0');
        }
        // Ensure file is not too large
        $filesize = $_FILES['file']['size']; // bytes
        $filesizeMB = $_FILES['file']['size']/1024/1024; // MB
        if ($filesizeMB > maxUploadSizeAttachment()) {
            // Delete uploaded file from server
            unlink($_FILES['file']['tmp_name']);
            // Set error msg
            $msg = '<code>'.$_FILES['file']['name'].'</code><br>'.$lang['sendit_03'] . ' (<b>' . round_up($filesizeMB) . ' MB</b>)'.$lang['period'].' ' .
                $lang['sendit_04'] . ' ' . maxUploadSizeAttachment() . ' MB ' . $lang['sendit_05'];
            exit($msg);
        }
        // Store the file
        $doc_id = Files::uploadFile($_FILES['file'], PROJECT_ID);
        if ($doc_id == 0) exit('0');
        // Add file
        $sql = "update redcap_econsent_forms set consent_form_pdf_doc_id = $doc_id, consent_form_richtext = null
                where consent_id = $consent_id and consent_form_id = $consent_form_id";
        if (!db_query($sql)) $doc_id = 0;
        // Return doc_id
        exit($doc_id."");
    }

    // Save consent form
    public function saveConsentForm($consent_id, $consent_form_id=null)
    {
        global $Proj, $lang;
        // Default error response
        $html = '0';
        // Get survey-level consent attributes
        if (!isinteger($consent_id) || !($consent_form_id == null || isinteger($consent_form_id))) exit($html);
        $consentSettings = self::getEconsentSettingsById($consent_id);
        if (empty($consentSettings)) exit($html);
        // The version number and consent_form_location_field are required
        if (trim($_POST['version']) == '' || trim($_POST['consent_form_location_field']) == '' || !isset($Proj->metadata[$_POST['consent_form_location_field']])) {
            $html = '-1';
            exit($html);
        }
        // If the current version number exists for this consent_id/DAG/Lang combo, return an error
        $sql = "select 1 from redcap_econsent_forms where consent_id = $consent_id and version = '" . db_escape($_POST['version']) . "'
                and consent_form_filter_dag_id ".(isinteger($_POST['consent_form_filter_dag_id']) ? "= '{$_POST['consent_form_filter_dag_id']}'" : "is null")."
                and consent_form_filter_lang_id ".($_POST['consent_form_filter_lang_id'] != '' ? "= '".db_escape($_POST['consent_form_filter_lang_id'])."'" : "is null")."
                limit 1";
        $q = db_query($sql);
        if (db_num_rows($q)) {
            $html = '-2';
            exit($html);
        }
        // Save/update location field
        $sql = "update redcap_econsent set consent_form_location_field = ? where consent_id = ? and project_id = ?";
        db_query($sql, [$_POST['consent_form_location_field'], $consent_id, $Proj->project_id]);
        // If an existing version already exists for this same consent_id/dag_id/lang_id, set it as inactive
        $sql = "update redcap_econsent_forms set consent_form_active = null
                where consent_id = $consent_id 
                and consent_form_filter_dag_id ".(isinteger($_POST['consent_form_filter_dag_id']) ? "= '{$_POST['consent_form_filter_dag_id']}'" : "is null")."
                and consent_form_filter_lang_id ".($_POST['consent_form_filter_lang_id'] != '' ? "= '".db_escape($_POST['consent_form_filter_lang_id'])."'" : "is null");
        if (!db_query($sql)) exit($html);
        // Add posted values to array
        $consentForms = [];
        $consentForms['consent_id'] = $consent_id;
        $consentForms['consent_form_active'] = '1';
        $consentForms['creation_time'] = NOW;
        $consentForms['uploader'] = UI_ID;
        $consentForms['version'] = strip_tags($_POST['version']);
        $consentForms['consent_form_pdf_doc_id'] = isinteger($_POST['consent_form_pdf_doc_id']) ? $_POST['consent_form_pdf_doc_id'] : null;
        $consentForms['consent_form_richtext'] = $_POST['consent_form_richtext'];
        if ($consentForms['consent_form_pdf_doc_id'] != null) $consentForms['consent_form_richtext'] = null;
        $consentForms['consent_form_filter_dag_id'] = isinteger($_POST['consent_form_filter_dag_id']) ? $_POST['consent_form_filter_dag_id'] : null;
        $consentForms['consent_form_filter_lang_id'] = strip_tags($_POST['consent_form_filter_lang_id']);
        // If the pdf doc_id already exists for a previous/existing consent form, then we need to copy it to get a new doc_id for the new consent form/version
        if (isinteger($_POST['consent_form_pdf_doc_id'])) {
            $sql = "select 1 from redcap_econsent_forms where consent_form_pdf_doc_id = {$consentForms['consent_form_pdf_doc_id']} and consent_id = $consent_id limit 1";
            if (db_num_rows(db_query($sql))) {
                $consentForms['consent_form_pdf_doc_id'] = REDCap::copyFile($consentForms['consent_form_pdf_doc_id'], $Proj->project_id);
            }
        }
        // Add consent form to table
        $sql = "insert into redcap_econsent_forms (".implode(", ", array_keys($consentForms)).") values (".prep_implode($consentForms, true, true).")";
        $q = db_query($sql);
        if (!$q) exit($html);
        $consent_form_id = db_insert_id();
        // Logging
        $survey_title = strip_tags($Proj->surveys[$consentSettings['survey_id']]['form_name']);
        Logging::logEvent($sql, "redcap_econsent_forms", "MANAGE", PROJECT_ID, "consent_form_id = $consent_form_id", "Add new consent form for instrument \"$survey_title\" (consent_form_id = $consent_form_id)");
        // Return consent_form_id
        print $consent_form_id;
    }

    // Remove a consent form
    public function removeConsentForm($consent_id, $consent_form_id)
    {
        $sql = "update redcap_econsent_forms 
                set consent_form_active = null 
                where consent_id = ? and consent_form_id = ?";
        if (db_query($sql, [$consent_id, $consent_form_id])) {
            print RCView::tt('econsent_132');
            $consentSettings = self::getEconsentSettingsById($consent_id);
            $Proj = new Project();
            $survey_title = $Proj->surveys[$consentSettings['survey_id']]['form_name'];
            Logging::logEvent($sql, "redcap_econsent_forms", "MANAGE", PROJECT_ID, "consent_form_id = $consent_form_id", "Remove consent form for instrument \"$survey_title\" (consent_form_id = $consent_form_id)");
        }
    }

    // Display the consent form inline in a dialog
    public function viewConsentForm($consent_id, $consent_form_id)
    {
        $consentForms = self::getConsentFormsByConsentId($consent_id, $consent_form_id);
        if (!isinteger($consentForms['consent_form_pdf_doc_id'])) exit("ERROR!");
        list ($mimeType, $docName, $fileContent) = Files::getEdocContentsAttributes($consentForms['consent_form_pdf_doc_id']);
        header('Content-type: application/pdf');
        header('Content-disposition: inline; filename="'.$docName.'"');
        print $fileContent;
    }

    public function getActiveMlmLanguages($project_id)
    {
        $mlmLanguages = \MultiLanguageManagement\MultiLanguage::readConfig($project_id, true);
        $langs = [];
        foreach ($mlmLanguages as $key=>$attr1) {
            if (!isset($attr1['active']) || $attr1['active'] != '1') continue;
            $langs[$key] = $attr1['display'];
        }
        return $langs;
    }

    // Display add consent form dialog
    public function addConsentForm($consent_id)
    {
        global $Proj, $lang;
        $iMagickInstalled = PDF::iMagickInstalled();
        // Default error response
        $html = '0';
        // Get survey-level consent attributes
        $consentSettings = self::getEconsentSettingsById($consent_id);
        // Get attributes for this consent form
        $consentForms = self::getConsentFormsByConsentId($consent_id, null, true);

        // User is adding a new consent form OR
        // User is adding a new version of an existing consent form
        $html = "";
        $consentForms = getTableColumns('redcap_econsent_forms');

        // Get array of all Descriptive fields on this survey instrument
        $descriptiveFields = [''=>'-- '.$lang['random_02'].' --'];
        foreach ($Proj->metadata as $this_field=>$attr1) {
            if ($attr1['element_type'] != 'descriptive') continue;
            if ($attr1['form_name'] != $Proj->surveys[$consentSettings['survey_id']]['form_name']) continue;
            // Clean the label
            $attr1['element_label'] = trim(str_replace(array("\r\n", "\n"), array(" ", " "), strip_tags($attr1['element_label']."")));
            // Truncate label if long
            if (mb_strlen($attr1['element_label']) > 65) {
                $attr1['element_label'] = trim(mb_substr($attr1['element_label'], 0, 47)) . "... " . trim(mb_substr($attr1['element_label'], -15));
            }
            $descriptiveFields[$this_field] = "$this_field \"{$attr1['element_label']}\"";
        }

        // Get MLM languages, if any
        $mlmLanguages = $this->getActiveMlmLanguages($Proj->project_id);
        $mlmLanguages[''] = empty($mlmLanguages) ? RCView::tt('econsent_54') : RCView::tt('econsent_55');
        ksort($mlmLanguages);

        // Get MLM languages, if any
        $dags1 = $Proj->getGroups();
        $dags = [''=>(empty($dags1) ? RCView::tt('econsent_57') : RCView::tt('econsent_58'))];
        foreach ($dags1 as $key=>$attr1) {
            $dags[$key]  = $attr1;
        }

        // Hidden elements
        $html .= RCView::hidden(['name'=>'consent_id', 'value'=>$consent_id]);

        // Display consent form section
        $html .= RCView::div(array('class'=>'mb-2'),
            RCView::tt('econsent_52')
        );
        $html .= RCView::div(array('class'=>'mb-3'),
            RCView::tt('econsent_166')
        );

        // Version
        $well = '';
        $well .= RCView::div(array('class'=>'mb-1 boldish'),
            RCView::fa('fa-solid fa-hashtag mr-1') . RCView::tt('econsent_63') .
            RCView::text(['id'=>'version', 'name'=>'version', 'class'=>'x-form-text x-form-field ml-2', 'style'=>'max-width:110px;', 'placeholder'=>'1.0', 'valorig'=>$consentForms['version'], 'value'=>$consentForms['version']])
        );
        $well .= RCView::div(array('class'=>'mb-1 fs12 text-secondary lineheight11'),
            RCView::tt('econsent_77')
        );
        // Descriptive field anchor
        $descriptiveFieldsNote = count($descriptiveFields) === 1 ? RCView::span(array('class'=>'text-dangerrc ml-2 fs11'), RCView::fa('fas fa-triangle-exclamation mr-1').RCView::tt('econsent_175')) : "";
        $well .= RCView::div(array('class'=>'mt-4 mb-1 boldish'),
            RCView::fa('fa-solid fa-location-dot mr-1') . RCView::tt('econsent_67') .
            RCView::select(['id'=>'consent_form_location_field', 'name'=>'consent_form_location_field', 'class'=>'x-form-text x-form-field ml-2', 'style'=>'max-width:400px;'], $descriptiveFields, $consentSettings['consent_form_location_field'], 300) .
            $descriptiveFieldsNote
        );
        $well .= RCView::div(array('class'=>'mb-1 fs12 text-secondary lineheight11'),
            RCView::tt('econsent_53')
        );
        // DAG drop-down
        $noDagsIcon = count($dags) === 1 ? RCView::fa('fas fa-ban text-dangerrc ml-1') : "";
        $well .= RCView::div(array('class'=>'mt-4 mb-1 boldish'),
            RCView::fa('fa-solid fa-users mr-1') . RCView::tt('econsent_69') .
            RCView::select(['name'=>'consent_form_filter_dag_id', 'class'=>'x-form-text x-form-field ml-2', 'style'=>'max-width:400px;'], $dags, $consentForms['consent_form_filter_dag_id'], 250) .
            $noDagsIcon
        );
        $well .= RCView::div(array('class'=>'mb-1 fs12 text-secondary lineheight11'),
            RCView::tt('econsent_59')
        );
        // MLM language drop-down
        $noLangsIcon = count($mlmLanguages) === 1 ? RCView::fa('fas fa-ban text-dangerrc ml-1') : "";
        $well .= RCView::div(array('class'=>'mt-4 mb-1 boldish'),
            RCView::fa('fa-solid fa-globe mr-1') . RCView::tt('econsent_68') .
            RCView::select(['name'=>'consent_form_filter_lang_id', 'class'=>'x-form-text x-form-field ml-2', 'style'=>'max-width:400px;'], $mlmLanguages, $consentForms['consent_form_filter_lang_id'], 250) .
            $noLangsIcon
        );
        $well .= RCView::div(array('class'=>'fs12 text-secondary lineheight11'),
            RCView::tt('econsent_56')
        );

        $html .= RCView::div(array('class'=>'well'),
            $well
        );

        // Consent form as inline PDF file
        $pdfInfo = "";
        if ($consentForms['consent_form_pdf_doc_id'] != '') {
            $pdfFileName = Files::getEdocName($consentForms['consent_form_pdf_doc_id']);
            $pdfInfo = RCView::div(['class'=>'mb-2', 'id'=>'consent-form-filename'],
                RCView::div(['class'=>''],
                    RCView::tt('econsent_90', 'span', ['class'=>'boldish mr-1']).
                    RCView::span(['class'=>'text-dangerrc'],
                        RCView::fa('far fa-file-pdf fs14 mr-1 ml-1').$pdfFileName
                    ) .
                    RCView::a(['href'=>'javascript:;', 'class'=>'text-danger fs16 ml-2', 'onclick'=>"$('#consent-form-filename').hide();$('#consent_form_pdf_doc_id_parent').show();$('#consent_form_pdf_doc_id_num').val('');",
                        'title'=>RCView::tt_attr('form_renderer_24')],
                        RCView::fa('fa-solid fa-xmark')
                    )
                )
            );
        }
        $tab2 = RCView::div(array('class'=>'mt-2 mb-2'),
            RCView::div(array('class'=>'mb-2 mx-2'),
                RCView::tt('econsent_89')
            ) .
            $pdfInfo .
            RCView::hidden(array('id'=>'consent_form_pdf_doc_id_num', 'name'=>'consent_form_pdf_doc_id', 'class'=>'', 'value'=>$consentForms['consent_form_pdf_doc_id'])) .
            RCView::div(array('class'=>'mb-5 pb-5', 'id'=>'consent_form_pdf_doc_id_parent', 'style'=>($pdfInfo == '' ? '' : 'display:none;')),
                RCView::tt('econsent_91', 'span', ['class'=>'mr-1 boldish']) .
                RCView::file(array('id'=>'consent_form_pdf_doc_id')) .
                RCView::tt('setup_53', 'a', ['href'=>'javascript:;', 'class'=>'ml-2 fs11 opacity75', 'style'=>'text-decoration:underline;', 'onclick'=>"$('#consent_form_pdf_doc_id').val('');"])
            )
        );
        // Consent form as rich text
        $tab1 = RCView::div(array('class'=>'mt-2 mb-3'),
            RCView::div(array('class'=>'mt-1 mb-2 boldish '.($iMagickInstalled ? "hide" : "")),
                RCView::tt('econsent_65')
            ) .
            RCView::div(array('class'=>'mb-2 mx-2'),
                RCView::tt('econsent_88') . " " . RCView::tt('econsent_186') . " " . RCView::tt('econsent_189')
            ) .
            RCView::div(array('class'=>'mb-5'),
                RCView::textarea(array('id'=>'consent_form_richtext', 'name'=>'consent_form_richtext', 'class'=>'x-form-field notesbox mceEditor', 'style'=>'width:99%;height:150px;'), $consentForms['consent_form_richtext'])
            )
        );
        // Tabs
        $tab1active = $consentForms['consent_form_pdf_doc_id'] == '' || !$iMagickInstalled ? "active" : "";
        $tab1activeB = $consentForms['consent_form_pdf_doc_id'] == '' || !$iMagickInstalled ? "show active" : "";
        $tab2active = $consentForms['consent_form_pdf_doc_id'] == '' || !$iMagickInstalled ? "" : "active";
        $tab2activeB = $consentForms['consent_form_pdf_doc_id'] == '' || !$iMagickInstalled ? "" : "show active";
        $displayInlinePdfOption = $iMagickInstalled ? "" : "hide";
        $html .= <<<EOF
            <div class="container mt-4 px-0">
              <ul class="nav nav-tabs $displayInlinePdfOption" id="myTab" role="tablist">
                <li class="nav-item boldish" role="presentation">
                  <button class="nav-link $tab1active" id="tab1-tab" data-bs-toggle="tab" data-bs-target="#tab1" type="button" role="tab" aria-controls="tab1" aria-selected="true">{$lang['econsent_65']}</button>
                </li>
                <li class="nav-item boldish" role="presentation">
                  <button class="nav-link $tab2active" id="tab2-tab" data-bs-toggle="tab" data-bs-target="#tab2" type="button" role="tab" aria-controls="tab2" aria-selected="false">{$lang['econsent_66']}</button>
                </li>
              </ul>
              <div class="tab-content" id="myTabContent">
                <div class="tab-pane fade $tab1activeB" id="tab1" role="tabpanel" aria-labelledby="tab1-tab">
                  $tab1
                </div>
                <div class="tab-pane fade $tab2activeB $displayInlinePdfOption" id="tab2" role="tabpanel" aria-labelledby="tab2-tab">
                  $tab2
                </div>
              </div>
            </div>
EOF;
        // Place all HTML inside form
        $html = "<form id='addConsentForm' method='post' action='".PAGE_FULL."'>$html</form>";

        // Output response
        print $html;
    }

    // Re-enable e-Consent for a survey
    public function reenable($consent_id, $survey_id)
    {
        global $Proj;
        $survey_title = $Proj->surveys[$survey_id]['form_name'];
        if (!isinteger($consent_id)) exit('0');
        $sql = "update redcap_econsent set active = 1 where consent_id = ?";
        if (db_query($sql, $consent_id)) {
            print RCView::tt_i('econsent_97',[$survey_title]);
            Logging::logEvent($sql, "redcap_econsent", "MANAGE", PROJECT_ID, "consent_id = $consent_id", "Re-enable e-Consent for instrument \"$survey_title\"");
        } else {
            exit('0');
        }
    }

    // Disable e-Consent for a survey
    public function disable($consent_id, $survey_id)
    {
        global $Proj;
        $survey_title = $Proj->surveys[$survey_id]['form_name'];
        if (!isinteger($consent_id)) exit('0');
        $sql = "update redcap_econsent set active = 0 where consent_id = ?";
        if (db_query($sql, $consent_id)) {
            print RCView::tt_i('econsent_98',[$survey_title]);
            Logging::logEvent($sql, "redcap_econsent", "MANAGE", PROJECT_ID, "consent_id = $consent_id", "Disable e-Consent for instrument \"$survey_title\"");
        } else {
            exit('0');
        }
    }

    // Display AJAX output for Edit eConsent Setup dialog
    // Existing consent items will have consent_id, whereas non-existing will have survey_id instead.
    public function editSetup($consent_id=null, $survey_id=null)
    {
        global $lang, $Proj, $pdf_econsent_system_custom_text;
        // Validate $consent_id
        if ($consent_id === '') $consent_id = null;
        if ($survey_id === '') $survey_id = null;
        if ($consent_id !== null && (!isinteger($consent_id) || $consent_id < 1)) exit('0');
        if ($survey_id !== null && (!isinteger($survey_id) || $survey_id < 1)) exit('0');

        // Get econsent item attributes
        $attr = ($consent_id === null) ? getTableColumns('redcap_econsent') : self::getEconsentSettingsById($consent_id);
        // If a participant-facing survey eConsent, get survey's form_name
        if ($survey_id !== null) {
            $form_name = $Proj->surveys[$survey_id]['form_name'];
            $title = $Proj->surveys[$survey_id]['title'];
        } else {
            $survey_id = $attr['survey_id'] ?? null;
            $form_name = $attr['survey_id'] == '' ? null : ($Proj->surveys[$attr['survey_id']]['form_name'] ?? null);
            $title = $attr['survey_id'] == '' ? "" : ($Proj->surveys[$attr['survey_id']]['title'] ?? "");
        }

        // Get all consent forms for this consent id/item
        $consentForms = self::getConsentFormsByConsentId($consent_id, null, true);
        if (!empty($consentForms)) {
            // Set legacy version to blank if consent forms exist
            $attr['version'] = '';
        }

        // Get Record Snapshot settings for this survey
        $rs = new PdfSnapshot();
        $snapshot = getTableColumns('redcap_pdf_snapshots');
        foreach ($rs->getSnapshots($Proj->project_id, true, true) as $attr2) {
            if ($attr2['trigger_surveycomplete_survey_id'] == $survey_id) {
                $snapshot = $attr2;
                break;
            }
        }

        // Output dialog HTML
        ?>
        <form id='editSetupForm' method='post' action='<?=PAGE_FULL?>'>
        <input type="hidden" name="version_current" value="<?=htmlspecialchars(label_decode($attr['version']), ENT_QUOTES)?>">
        <input type="hidden" name="consent_id" value="<?=$consent_id?>">
        <input type="hidden" name="survey_id" value="<?=$survey_id?>">

        <div class="fs16 mb-3 text-primaryrc"><?=RCView::fa('fa-solid fa-user-pen mr-1').RCView::tt('econsent_51')." \"".RCView::b(RCView::escape($title))."\" (".RCView::i(['class'=>'fs14'], $form_name).")"?></div>

        <div style="">

            <!-- Instructions -->
            <div class="mb-3 lineheight12">
                <?php echo $lang['econsent_146'] ?>
            </div>

            <!-- Primary settings -->
            <div class="well">
                <div class="mb-3 fs14 text-primaryrc font-weight-bold">
                    <?php echo $lang['econsent_147'] ?>
                </div>

                <div class="mb-2">
                    <label for="allow_edit">
                        <input type="checkbox" id="allow_edit" name="allow_edit" <?php echo (($Proj->project['allow_econsent_allow_edit'] && $attr['allow_edit'] == '1') ? 'checked' : ($Proj->project['allow_econsent_allow_edit'] ? '' : 'disabled')); ?>>
                        <?php print ($Proj->project['allow_econsent_allow_edit'] ? RCView::tt('survey_1254') : RCView::tt('survey_1254','span',['class'=>'text-secondary']) . RCView::tt('global_23','code',['class'=>'ml-2 boldish'])) ?>
                    </label>
                </div>
                <div class="mb-2">
                    <?php
                    print 	$lang['survey_1164'];
                    print 	RCView::select(array('name'=>'firstname_field', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 3px 0 20px;width:250px;'),
                        Form::getFieldDropdownOptions(true), $attr['firstname_field']);
                    if ($Proj->longitudinal) {
                        print 	$lang['global_107'];
                        print 	RCView::select(array('name'=>'firstname_event_id', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:6px;width:150px;'),
                            REDCap::getEventNames(false, true), $attr['firstname_event_id']);
                    }
                    ?>
                </div>
                <div class="mb-2">
                    <?php
                    print 	$lang['survey_1165'] .
                        RCView::select(array('name'=>'lastname_field', 'class'=>'x-form-text x-form-field', 'style'=>'margin:0 3px 0 22px;width:250px;'),
                            Form::getFieldDropdownOptions(true), $attr['lastname_field']);
                    if ($Proj->longitudinal) {
                        print 	$lang['global_107'];
                        print 	RCView::select(array('name'=>'lastname_event_id', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:6px;width:150px;'),
                            REDCap::getEventNames(false, true), $attr['lastname_event_id']);
                    }
                    ?>
                </div>
                <div class="fs11 text-secondary">
                    <?php print $lang['survey_1191'] ?>
                </div>
            </div>

            <!-- Optional settings -->
            <div class="well">
                <div class="mb-3 fs14 text-primaryrc font-weight-bold">
                    <?php echo $lang['econsent_148'] ?>
                </div>
                <div class="mb-2" style="<?=($attr['version'] == '' ? 'display:none;' : '')?>">
                    <div class="d-inline-block" style="width:250px;"><?php print $lang['survey_1162'] ?></div>
                    <input style="vertical-align:middle;width:250px;" name="version" type="text" value="<?php echo htmlspecialchars(label_decode($attr['version']), ENT_QUOTES) ?>" class="x-form-text x-form-field" onkeydown="if(event.keyCode==13){return false;}">
                    <span style="margin-left:10px;font-size:11px;color:#888;">e.g., 4</span>
                </div>
                <div class="mb-2">
                    <div class="d-inline-block" style="width:250px;"><?php print $lang['survey_1166'] ?></div>
                    <?php
                    print 	RCView::select(array('name'=>'dob_field', 'class'=>'x-form-text x-form-field', 'style'=>'width:250px;'),
                        Form::getFieldDropdownOptions(true), $attr['dob_field']);
                    if ($Proj->longitudinal) {
                        print 	$lang['global_107'];
                        print 	RCView::select(array('name'=>'dob_event_id', 'class'=>'x-form-text x-form-field', 'style'=>'margin-left:6px;max-width:120px;'),
                            REDCap::getEventNames(false, true), $attr['dob_event_id']);
                    }
                    ?>
                </div>
                <div class="mb-2">
                    <div class="d-inline-block" style="width:250px;"><?php print $lang['econsent_165'].$lang['colon'] ?></div>
                    <input style="vertical-align:middle;width:250px;" name="type_label" type="text" value="<?php echo htmlspecialchars(label_decode($attr['type_label']), ENT_QUOTES) ?>" class="x-form-text x-form-field" onkeydown="if(event.keyCode==13){return false;}">
                    <span style="margin-left:10px;font-size:11px;color:#888;">e.g., Pediatric</span>
                    <div style="font-size:11px;color:#888;"><?php print $lang['econsent_180'] ?></div>
                </div>
                <div class="mb-4">
                    <div class="d-inline-block" style="width:250px;"><?php print $lang['econsent_118'] ?></div>
                    <input style="vertical-align:middle;width:250px;" name="custom_econsent_label" type="text" value="<?php echo htmlspecialchars(label_decode($attr['custom_econsent_label']), ENT_QUOTES) ?>" class="x-form-text x-form-field" onkeydown="if(event.keyCode==13){return false;}">
                    <span style="margin-left:10px;font-size:11px;color:#888;">e.g., PID [project-id] - [last_name]</span>
                    <div style="margin-top:5px;font-size:11px;color:#888;">
                        <?php print $lang['econsent_168'] ?>
                        <button class="btn btn-xs btn-defaultrc ml-2" style="font-size:11px;padding:0px 3px 1px;line-height:14px;" onclick="codebookPopup();return false;"><i class="fas fa-book" style="margin:0 2px 0 1px;"></i> <?=RCView::tt('design_482')?></button>
                        <button class="btn btn-xs btn-rcgreen btn-rcgreen-light ml-2" style="font-size:11px;padding:0px 3px 1px;line-height:14px;"  onclick="smartVariableExplainPopup();return false;">[<i class="fas fa-bolt fa-xs" style="margin:0 1px;"></i>] <?=RCView::tt('global_146')?></button>
                    </div>
                </div>
                <!-- Signature fields -->
                <div class="mb-2 text-primaryrc boldish">
                    <?php print $lang['survey_1262'] ?>
                </div>
                <div class="mb-3 lineheight12">
                    <?php print $lang['survey_1263'] ?>
                </div>
                <?php
                $sigDDoptions = self::getFieldDropdownOptionsSignatures(PROJECT_ID, $form_name, true);
                $sigFieldHtml = '';
                for ($sn=1; $sn<=5; $sn++) {
                    $this_signature_field = 'signature_field'.$sn;
                    $sigFieldHtml .= RCView::div(array('class' => 'my-1 signature_field_div'),
                        RCView::span(['class'=>'boldish'], $lang['survey_1261'] . ' #' . $sn . $lang['colon']) .
                        RCView::select(array('name' => $this_signature_field, 'class' => 'x-form-text x-form-field', 'style' => 'margin:0 3px 0 10px;max-width:200px;'),
                            $sigDDoptions, $attr[$this_signature_field] ?? "")
                    );
                }
                print $sigFieldHtml;
                ?>
                <?php
                if (empty($sigDDoptions)) {
                    ?><div class="m-1" style="color:#C00000;"><i class="fas fa-info-circle"></i> <?php print $lang['survey_1268'] ?></div><?php
                } else {
                    ?>
                    <div id="select-more-sigs" style="margin:15px 0 4px;font-size:11px;line-height:12px;color:#666;">
                        <button class="btn btn-defaultrc btn-xs fs11" onclick="return false;"><i class="fas fa-plus fs10"></i> <?php print $lang['survey_1267'] ?></button>
                    </div>
                    <?php
                }
                ?>
            </div>

            <!-- Storage location -->
            <div class="well">
                <div class="mb-3 fs14 text-primaryrc font-weight-bold">
                    <?php print $lang['econsent_15'] ?>
                </div>
                <div>
                    <?php
                    print   RCView::label(['class'=>'mb-1 d-block'],
                        RCView::fa('fas fa-check mr-1') .
                        RCView::tt('econsent_103', 'span', ['class'=>'boldish']) .
                        ($GLOBALS['pdf_econsent_filesystem_type'] != '' && $Proj->project['store_in_vault_snapshots_containing_completed_econsent'] ? RCView::tt('econsent_182', 'span', ['class'=>'ml-2', 'style'=>'color:#555;']) : "")
                    );
                    $pdfSaveEvents = [''=>$lang['survey_1306']];
                    foreach ($Proj->eventInfo as $thisEventId=>$attr3) {
                        $pdfSaveEvents[$thisEventId] = $attr3['name_ext'];
                    }
                    $pdfSaveFields = Form::getFieldDropdownOptions(true, false, false, false, '', true, true, false, 'file', $lang['econsent_76']);
                    $pdfSaveFieldsEmpty = count($pdfSaveFields) <= 1;
                    $pdfSaveFieldsDisabled = $pdfSaveFieldsEmpty ? "disabled" : "";
                    $pdf_save_to_field_checkbox_checked = $snapshot['pdf_save_to_field'] != '' ? "checked" : "";
                    print RCView::label(array('class'=>'mb-1 d-block '.($pdfSaveFieldsEmpty ? 'text-tertiary' : ''), 'for'=>'pdf_save_to_field_checkbox'),
                        RCView::checkbox(array('id'=>'pdf_save_to_field_checkbox', 'name'=>'pdf_save_to_field_checkbox', 'onclick'=>"if (!$(this).prop('checked')) $('select[name=pdf_save_to_field]').val('');", $pdf_save_to_field_checkbox_checked=>$pdf_save_to_field_checkbox_checked, $pdfSaveFieldsDisabled=>$pdfSaveFieldsDisabled)) .
                        RCView::tt('econsent_104', 'span', ['class'=>'mr-2 boldish']) .
                        RCView::select(array('name'=>'pdf_save_to_event_id', 'class'=>'x-form-text x-form-field fs12 mr-1', 'style'=>'max-width:150px;'.($Proj->longitudinal ? "" : "display:none;"), $pdfSaveFieldsDisabled=>$pdfSaveFieldsDisabled),
                            $pdfSaveEvents, $snapshot['pdf_save_to_event_id'], 300) .
                        RCView::select(array('name'=>'pdf_save_to_field', 'class'=>'x-form-text x-form-field fs12', 'style'=>'max-width:350px;', 'onchange'=>"$('#pdf_save_to_field_checkbox').prop('checked',($(this).val()!=''));", $pdfSaveFieldsDisabled=>$pdfSaveFieldsDisabled),
                            $pdfSaveFields, $snapshot['pdf_save_to_field'])
                    );
                    ?>
                </div>
                <div>
                    <?php
                    print   RCView::label(['class'=>'mb-1 d-block'],
                                RCView::fa('fas fa-check mr-1') .
                                RCView::tt('survey_1370') .
                                RCView::tt('survey_1371','span',['class'=>'ml-1'])
                            );
                    ?>
                </div>
            </div>

            <!-- Filename prefix -->
            <div class="well">
                <?php
                // Filename prefix
                if ($snapshot['custom_filename_prefix'] == null) $snapshot['custom_filename_prefix'] = 'pid[project-id]_form[instrument-label]_id[record-name]'; // default filename prefix
                print RCView::div(array('class'=>'mb-3 fs14 text-primaryrc font-weight-bold'),
                        RCView::tt('econsent_121')
                    ) .
                    RCView::div(array('class'=>'mb-3 fs13 lineheight12'),
                        RCView::tt('econsent_122')
                    ) .
                    RCView::div(array('class'=>'input-group', 'style'=>'width:97%;'),
                        RCView::tt('docs_19', 'span', ['class'=>'input-group-text py-1 px-2 boldish fs12']) .
                        RCView::text(['name'=>'custom_filename_prefix', 'class'=>'form-control py-1 px-2 fs12', 'style'=>'text-align:end;', 'value'=>$snapshot['custom_filename_prefix']]) .
                        RCView::span(['class'=>'input-group-text py-1 px-2 fs12', 'style'=>'color:#cf357c;'], '_YYYY-MM-DD_HHMMSS.pdf')
                    ) .
                    RCView::div(['class'=>'fs11 mt-1 ml-1', 'style'=>'color:#888;'], 'e.g., [last_name]_[first_name]_[dob]_record[record-name]');
                ?>
            </div>

            <!-- Custom notes -->
            <div class="well">
                <div class="mb-3 fs13 text-primaryrc">
                    <?php print $lang['econsent_81'] ?>
                </div>
                <?php print $lang['calendar_popup_11'].$lang['colon'] ?>
                <input style="margin-left:5px;vertical-align:middle;width:80%;" name="notes" type="text" value="<?php echo htmlspecialchars(label_decode($attr['notes']??""), ENT_QUOTES) ?>" class="x-form-text x-form-field" onkeydown="if(event.keyCode==13){return false;}">
            </div>

        </div>
        <?php

        // Custom e-Consent text (from system-level setting)
        if (isset($pdf_econsent_system_custom_text) && trim($pdf_econsent_system_custom_text) != '') {
            // Custom message
            print RCView::div(array('class'=>'mt-4 mb-3'), nl2br(decode_filter_tags($pdf_econsent_system_custom_text)));
        }
        print "</form>";
    }

    // Return array of all econsent items for a project (all types, including inactive ones)
    public function getAllEconsents($project_id, $activeOnly=false)
    {
        if (!$GLOBALS['pdf_econsent_system_enabled']) return [];
        $sql = "select * from redcap_econsent where project_id = ?";
        if ($activeOnly) $sql .= " and active = 1";
        $sql .= " order by survey_id, active, version, consent_id";
        $q = db_query($sql, $project_id);
        $rows = [];
        while ($row = db_fetch_assoc($q)) {
            $rows[$row['consent_id']] = $row;
        }
        return $rows;
    }

    // Return array of single econsent item for a given survey
    public function getEconsentBySurveyId($survey_id)
    {
        $project_id = Survey::getProjectIdFromSurveyId($survey_id);
        $rows = $this->getAllEconsents($project_id);
        foreach ($rows as $row) {
            if ($row['survey_id'] != $survey_id) continue;
            return $row;
        }
        return [];
    }

    // Load the table of defined econsents on the setup page
    public function loadTable($displayInactive=false)
    {
        $Proj = new Project(PROJECT_ID);
        $eConsentSurveys = $this->getAllEconsents(PROJECT_ID);
        $eConsentSurveysEmpty = getTableColumns('redcap_econsent');

        // Create array of survey_ids=>consent_ids
        $surveyIdsConsentIds = [];
        foreach ($eConsentSurveys as $attr) {
            if (!isset($surveyIdsConsentIds[$attr['survey_id']])) $surveyIdsConsentIds[$attr['survey_id']] = [];
            $surveyIdsConsentIds[$attr['survey_id']][$attr['consent_id']] = $eConsentSurveys[$attr['consent_id']];
        }

        // Load any record snapshot settings
        $rss = new PdfSnapshot();
        $pdfSnapshots = $rss->getSnapshots(PROJECT_ID, true, true);
        $surveyIdsSnapshots = [];
        foreach ($pdfSnapshots as $attr) {
            $surveyIdsSnapshots[$attr['consent_id']] = $attr;
        }

        // Loop through all
        $rows = [];
        foreach ($Proj->surveys as $survey_id=>$attr)
        {
            $surveyRows = $surveyIdsConsentIds[$survey_id] ?? [$eConsentSurveysEmpty];
            foreach ($surveyRows as $row)
            {
                $consent_id = $row['consent_id'] ?? 0;

                // Don't display surveys that don't have e-Consent enabled
                if ($consent_id == 0) continue;

                // Hide inactive versions?
                if (!$displayInactive && $row['active'] == '0' && $consent_id > 0) continue;

                // Get record snapshot settings
                $snapshotAttr = $surveyIdsSnapshots[$consent_id] ?? [];

                // Common attributes
                $action_icons = "";
                if ($row['active']) {
                    $action_icons = RCView::button(['class' => 'btn btn-light btn-sm mr-3', 'data-bs-toggle'=>'tooltip', 'data-bs-original-title'=>RCView::tt_attr('econsent_43'), 'onclick' => "openSetupDialog($consent_id,$survey_id);"],
                                        RCView::fa('fa-solid fa-pencil')
                                    );
                }
                if ($row['active']) {
                    // Active
                    $active = RCView::div(['class'=>'form-check form-switch fs18 ml-3'], RCView::checkbox(['class'=>'form-check-input', 'onclick'=>"toggleEnableEconsent(this,$consent_id,$survey_id);", 'checked'=>'checked']) . RCView::label(['class'=>'form-check-label'], ""));
                    $inactive_class = '';
                } else {
                    // Inactive
                    $active = RCView::span(['class'=>'form-check form-switch fs18 ml-3'], RCView::checkbox(['class'=>'form-check-input', 'onclick'=>"toggleEnableEconsent(this,$consent_id,$survey_id);"]) . RCView::label(['class'=>'form-check-label'], ""));
                    $inactive_class = 'opacity75 text-secondary';
                }
                $title = RCView::div(['class' => 'wrap fs14 lineheight12 mb-2'],
                    "\"" . RCView::span(['class'=>'boldish'], RCView::escape($attr['title'])) . "\" ".RCView::span(['class'=>'text-secondary fs12 ml-1'], "({$attr['form_name']})")
                );
                $type_label = RCView::div(['class' => 'wrap fs12 '.$inactive_class], RCView::escape($row['type_label']));
                // $version = RCView::div(['class' => 'nowrap mr-3 ' . ($row['active'] ? 'fs14 boldish' : $inactive_class)], $row['version']);
                $save_location = RCView::div(['class' => 'nowrap fs11 mb-1 '.$inactive_class], RCView::fa('fas fa-folder-open fs14 mr-1') . RCView::tt('app_04'));
                if (($snapshotAttr['pdf_save_to_field']??'') != '') {
                    $save_location .= RCView::div(['class' => 'fs11 mb-1 '.$inactive_class],
                        RCView::span(['class'=>'nowrap mr-1'],
                            RCView::i(['class'=>'fa-solid fa-arrow-right-to-bracket fs14', 'style' => 'margin-right:6px;'], '') .
                            RCView::tt('econsent_20')
                        ) .
                        RCView::code(['class'=>'nowrap', 'style'=>'font-size:100%;'],
                            ($Proj->longitudinal && isinteger($snapshotAttr['pdf_save_to_event_id']) ? "[".$Proj->getUniqueEventNames($snapshotAttr['pdf_save_to_event_id'])."]" : "") .
                            "[".$snapshotAttr['pdf_save_to_field']."]"
                        )
                    );
                }
                if (mb_strlen($row['notes']??"") > 80) {
                    $row['notes'] = mb_substr($row['notes'], 0, 78)."...";
                }
                $notes = RCView::div(['class' => 'wrap fs11 '.$inactive_class], RCView::escape($row['notes']));

                // Get active consent forms, if any
                $consentFormLinks = "";
                $consentForms = self::getConsentFormsByConsentId($consent_id, null, true);
                $consentFormsInclInactive = self::getConsentFormsByConsentId($consent_id);
                foreach ($consentForms as $thisConsentForm) {
                    $context_sensitive = "";
                    // Language
                    if ($thisConsentForm['consent_form_filter_lang_id'] != '') {
                        $context_sensitive .=
                            " " . RCView::tt('data_entry_67') .
                            RCView::span(['class' => 'nowrap text-primaryrc ml-1 mr-1', 'data-bs-toggle' => 'tooltip', 'data-bs-original-title' => RCView::tt_js2('econsent_22')],
                                RCView::i(['class' => 'fas fa-globe fs12 mr-1'], '') .
                                $thisConsentForm['consent_form_filter_lang_id']
                            );
                    }
                    // DAG
                    if ($thisConsentForm['consent_form_filter_dag_id'] != '') {
                        $context_sensitive .=
                            ($thisConsentForm['consent_form_filter_lang_id'] != '' ? RCView::tt('global_43') : " " . RCView::tt('data_entry_67')) .
                            RCView::span(['class' => 'nowrap text-successrc ml-1', 'data-bs-toggle' => 'tooltip', 'data-bs-original-title' => RCView::tt_js2('global_78')],
                                RCView::fa('fas fa-users fs12 mr-1') .
                                $Proj->getUniqueGroupNames($thisConsentForm['consent_form_filter_dag_id'])
                            );
                    }
                    // Output line
                    $consentFormLinks .= RCView::div(['class' => 'wrap fs12 lineheight12', 'style'=>'margin-bottom:2px;'],
                        ($thisConsentForm['consent_form_pdf_doc_id'] != '' ? RCView::fa('far fa-file-pdf fs14 mr-1') : RCView::fa('far fa-file-lines fs14 mr-1')) .
                        RCView::tt('econsent_72') . " " . RCView::code(['style'=>'font-size:100%;'], "v".trim(filter_tags($thisConsentForm['version']))) .
                        RCView::span(['class' => 'nowrap'], trim($context_sensitive))
                    );
                }
                $consentFormLinks = RCView::div(['class'=>'mt-1 ml-2'],
                    $consentFormLinks .
                    RCView::div(['class'=>'mt-2'],
                        RCView::a(['class'=>'fs11 text-successrc', 'href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"openAddConsentFormDialog($consent_id,null)"],
                            RCView::fa('fa-solid fa-plus mr-1') . RCView::tt('econsent_45','')
                        ) .
                        (count($consentFormsInclInactive) == 0 ? "" :
                            RCView::a(['class'=>'fs11 text-primaryrc ml-3', 'href'=>'javascript:;', 'style'=>'text-decoration:underline;', 'onclick'=>"openViewConsentFormVersionsDialog($consent_id,$survey_id)"],
                                RCView::fa('fa-solid fa-list-check mr-1') . RCView::tt('econsent_117','')
                            )
                        )
                    )
                );
                // Add row
                $rows[] = [$active, $action_icons, $title . $consentFormLinks, $save_location, $type_label, $notes];
            }
        }
        // Output JSON
        header('Content-Type: application/json');
        echo json_encode_rc(['data' => $rows]);
    }

    // Is participant-facing eConsent enabled and active for a given survey?
    public static function econsentEnabledForSurvey($survey_id)
    {
        return !empty(self::getEconsentSurveySettings($survey_id));
    }

    // Return array of eConsent settings for a given survey that has participant-facing eConsent enabled and active
    public static function getEconsentSurveySettings($survey_id, $dag_id=null, $lang_id=null)
    {
        if (!isinteger($survey_id)) return [];
        $params = [$survey_id];
        $sql = "select f.*, e.*, ifnull(f.version, e.version) as version 
                from redcap_econsent e 
                left join redcap_econsent_forms f on e.consent_id = f.consent_id and f.consent_form_active = 1
                where e.active = 1 and e.survey_id = ?";
        $order_lang = $order_dag = "";
        if (isinteger($dag_id)) {
            $sql .= " and (f.consent_form_filter_dag_id is null or f.consent_form_filter_dag_id = ?)"; // Also return null DAG as a fallback
            $order_dag = "desc";
            $params[] = $dag_id;
        }
        if ($lang_id != null) {
            $sql .= " and (f.consent_form_filter_lang_id is null or f.consent_form_filter_lang_id = ?)"; // Also return null lang as a fallback
            $order_lang = "desc";
            $params[] = $lang_id;
        }
        $sql .= " order by f.consent_form_filter_dag_id $order_dag, f.consent_form_filter_lang_id $order_lang, e.version desc, e.consent_id desc, f.consent_form_id desc limit 1";
        $q = db_query($sql, $params);
        return db_fetch_assoc($q);
    }

    // Return array of eConsent settings for a given eConsent item by consent_id
    public static function getEconsentSettingsById($consent_id)
    {
        if (!isinteger($consent_id) || !$GLOBALS['pdf_econsent_system_enabled']) return [];
        $sql = "select * from redcap_econsent where consent_id = ?";
        $q = db_query($sql, $consent_id);
        return db_fetch_assoc($q);
    }

    // Return array of all eConsent settings for a given project
    public static function getEconsentSettings($project_id, $activeOnly=false)
    {
        if (!isinteger($project_id) || !$GLOBALS['pdf_econsent_system_enabled']) return [];
        $sql = "select * from redcap_econsent where project_id = ?";
        if ($activeOnly) $sql .= " and active = 1";
        $q = db_query($sql, $project_id);
        $rows = [];
        while ($row = db_fetch_assoc($q)) {
            $rows[$row['consent_id']] = $row;
        }
        return $rows;
    }

    // Return array of all consent forms from redcap_econsent_forms by consent_id
    public static function getConsentFormsByConsentId($consent_id, $consent_form_id=null, $activeOnly=false, $sortFirstByCreationTime=false)
    {
        if (!isinteger($consent_id) || !$GLOBALS['pdf_econsent_system_enabled']) return [];
        if ($consent_form_id !== null && !isinteger($consent_form_id)) return [];
        $params = [$consent_id];
        $sql2 = "";
        if ($consent_form_id !== null) {
            $params[] = $consent_form_id;
            $sql2 .= "and consent_form_id = ? ";
        }
        if ($activeOnly) {
            $params[] = 1;
            $sql2 .= "and consent_form_active = ? ";
        }
        $sql3 = $sortFirstByCreationTime ? "creation_time, " : "";
        $sql = "select * from redcap_econsent_forms where consent_id = ? $sql2 
                order by {$sql3}consent_form_filter_dag_id, consent_form_filter_lang_id, abs(version) desc, version desc, consent_form_id";
        $q = db_query($sql, $params);
        $rows = [];
        while ($row = db_fetch_assoc($q)) {
            $rows[] = $row;
        }
        if ($consent_form_id !== null) {
            return empty($rows) ? $rows : $rows[0];
        }
        return $rows;
    }

    // Return array of all e-Consent signature fields designated for a given survey
    public static function getSignatureFieldsByForm($project_id, $record, $form, $event_id, $instance=1)
    {
        $Proj = new Project($project_id);
        $survey_id = $Proj->forms[$form]['survey_id'];
        // Record in a DAG?
        $dag_id = Records::getRecordGroupId($project_id, $record);
        // Is there a current MLM language set?
        $context = \REDCap\Context::Builder()
            ->project_id($project_id)
            ->event_id($event_id)
            ->instrument($form)
            ->instance($instance)
            ->record($record)
            ->is_survey()
            ->survey_id($survey_id);
        $context = $context->Build();
        $lang_id = MultiLanguage::getCurrentLanguage($context);
        // Get the e-Consent settings for this instrument
        $eConsentSettings = Econsent::getEconsentSurveySettings($survey_id, $dag_id, $lang_id);
        // Loop through all signature fields
        $fields = array();
        for ($sn=1; $sn<=Survey::numEconsentSignatureFields; $sn++) {
            $this_pdf_econsent_signature_field = 'signature_field'.$sn;
            if (isset($eConsentSettings[$this_pdf_econsent_signature_field]) && $eConsentSettings[$this_pdf_econsent_signature_field] != '') {
                $fields[] = $eConsentSettings[$this_pdf_econsent_signature_field];
            }
        }
        return $fields;
    }

    // Get all options for drop-down displaying fields that could be used as e-Consent signature fields (text boxes + wet signature)
    public static function getFieldDropdownOptionsSignatures($project_id, $form, $returnOnlyRequiredFields=false)
    {
        $Proj = new Project($project_id);
        $rc_fields = array();
        $rc_fields[''] = '-- '.RCView::getLangStringByKey("random_02").' --';
        // Build an array of drop-down options listing all REDCap fields
        foreach (array_keys($Proj->forms[$form]['fields'] ?? []) as $this_field)
        {
            $attr1 = $Proj->metadata[$this_field];
            if ($this_field == $Proj->table_pk) continue;
            if ($attr1['element_type'] != 'textarea'
                && !($attr1['element_type'] == 'text' && ($attr1['element_validation_type'] == '' || $attr1['element_validation_type'] == 'int' || $attr1['element_validation_type'] == 'float'))
                && !($attr1['element_type'] == 'file' && $attr1['element_validation_type'] == 'signature')
            ) {
                continue;
            }
            // Return only required fields?
            if ($returnOnlyRequiredFields && $attr1['field_req'] != '1') continue;
            // Clean the label
            $attr1['element_label'] = trim(str_replace(array("\r\n", "\n"), array(" ", " "), strip_tags($attr1['element_label'])));
            // Truncate label if long
            if (mb_strlen($attr1['element_label']) > 65) {
                $attr1['element_label'] = trim(mb_substr($attr1['element_label'], 0, 47)) . "... " . trim(mb_substr($attr1['element_label'], -15));
            }
            $rc_fields[$this_field] = "$this_field \"{$attr1['element_label']}\"";
        }
        // Return all options
        return $rc_fields;
    }

    // Obtain the name, dob, econsent version and type for this record/form
    public static function getEconsentOptionsData($project_id, $record, $form, $dag_id=null, $lang_id=null, $removeIdentifiers=false)
    {
        $Proj = new Project($project_id);
        $surveySettings = Econsent::getEconsentSurveySettings($Proj->forms[$form]['survey_id'], $dag_id, $lang_id);
        if ($surveySettings === null) return ["", "", ""];
        // Set version and type string
        $versionText = $typeText = "";
        if ($surveySettings['version'] != '') {
            $versionText = $surveySettings['version'];
        }
        if ($surveySettings['type_label'] != '') {
            $typeText = $surveySettings['type_label'];
        }
        // Validate the event_ids
        $fields = $events = array();
        if ($surveySettings['firstname_field'] != '') {
            if (!isset($Proj->eventInfo[$surveySettings['firstname_event_id']])) {
                $surveySettings['firstname_event_id'] = $Proj->firstEventId;
            }
            $fields[] = $surveySettings['firstname_field'];
            $events[] = $surveySettings['firstname_event_id'];
        }
        if ($surveySettings['lastname_field'] != '') {
            if (!isset($Proj->eventInfo[$surveySettings['lastname_event_id']])) {
                $surveySettings['lastname_event_id'] = $Proj->firstEventId;
            }
            $fields[] = $surveySettings['lastname_field'];
            $events[] = $surveySettings['lastname_event_id'];
        }
        if ($surveySettings['dob_field'] != '') {
            if (!isset($Proj->eventInfo[$surveySettings['dob_event_id']])) {
                $surveySettings['dob_event_id'] = $Proj->firstEventId;
            }
            $fields[] = $surveySettings['dob_field'];
            $events[] = $surveySettings['dob_event_id'];
        }
        // Get data for these fields
        $nameDobText = $firstname = $lastname = $dob = "";
        if (!empty($fields)) {
            $data = Records::getData($Proj->project_id, 'array', $record, $fields, $events);
            // Combine first/last name, if separate (can also be single field with whole name)
            // First name
            $thisFieldForm = $Proj->metadata[$surveySettings['firstname_field']]['form_name'] ?? "";
            $thisFieldRepeating = $Proj->isRepeatingFormOrEvent($surveySettings['firstname_event_id'], $thisFieldForm);
            $thisFieldRepeatInstrument = "";
            if ($thisFieldRepeating) {
                $thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings['firstname_event_id'], $thisFieldForm) ? $thisFieldForm : "";
            }
            if ($surveySettings['firstname_field'] != '') {
                if ($thisFieldRepeating) {
                    $firstname = $data[$record]['repeat_instances'][$surveySettings['firstname_event_id']][$thisFieldRepeatInstrument][$_GET['instance']][$surveySettings['firstname_field']] ?? "";
                } else {
                    $firstname = $data[$record][$surveySettings['firstname_event_id']][$surveySettings['firstname_field']] ?? "";
                }
            }
            // Last name
            $thisFieldForm = $Proj->metadata[$surveySettings['lastname_field']]['form_name'] ?? "";
            $thisFieldRepeating = $Proj->isRepeatingFormOrEvent($surveySettings['lastname_event_id'], $thisFieldForm);
            $thisFieldRepeatInstrument = "";
            if ($thisFieldRepeating) {
                $thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings['lastname_event_id'], $thisFieldForm) ? $thisFieldForm : "";
            }
            if ($surveySettings['lastname_field'] != '') {
                if ($thisFieldRepeating) {
                    $lastname = $data[$record]['repeat_instances'][$surveySettings['lastname_event_id']][$thisFieldRepeatInstrument][$_GET['instance']][$surveySettings['lastname_field']] ?? '';
                } else {
                    $lastname = $data[$record][$surveySettings['lastname_event_id']][$surveySettings['lastname_field']] ?? '';
                }
            }
            // DOB
            $thisFieldForm = $Proj->metadata[$surveySettings['dob_field']]['form_name'] ?? "";
            $thisFieldRepeating = $Proj->isRepeatingFormOrEvent($surveySettings['dob_event_id'], $thisFieldForm);
            $thisFieldRepeatInstrument = "";
            if ($thisFieldRepeating) {
                $thisFieldRepeatInstrument = $Proj->isRepeatingForm($surveySettings['dob_event_id'], $thisFieldForm) ? $thisFieldForm : "";
            }
            if ($surveySettings['dob_field'] != '') {
                if ($thisFieldRepeating) {
                    $dob = $data[$record]['repeat_instances'][$surveySettings['dob_event_id']][$thisFieldRepeatInstrument][$_GET['instance']][$surveySettings['dob_field']] ?? '';
                } else {
                    $dob = $data[$record][$surveySettings['dob_event_id']][$surveySettings['dob_field']] ?? '';
                }
                $dob = trim($dob);
            }
            // Apply de-id?
            if ($removeIdentifiers) {
                if ($firstname != "") $firstname = DEID_TEXT;
                if ($lastname != "") $lastname = DEID_TEXT;
                if ($dob != "") $dob = DEID_TEXT;
			}
            // Add to array
            $nameDobArray = array();
            $wholename = trim("$firstname $lastname");
            if ($wholename != '') $nameDobArray[] = $wholename;
            if ($dob != '') $nameDobArray[] = $dob;
            $nameDobText = trim(implode(', ', $nameDobArray));
        }
        // Return both the name/dob and version/type as 2 parts of an array
        return array($nameDobText, $versionText, $typeText);
    }

    // Return consent_id of a stored consent form for a given record/event/survey/instance
    public static function getAttributesOfStoredConsentForm($record, $event_id, $survey_id, $instance=1)
    {
        $sql = "select * from redcap_surveys_pdf_archive 
                where record = ? and event_id = ? and survey_id = ? and instance = ?
                order by doc_id desc
                limit 1";
        $q = db_query($sql, [$record, $event_id, $survey_id, $instance]);
        return (db_num_rows($q) > 0 ? db_fetch_assoc($q) : null);
    }

    // Return consent_id of a stored consent form for a given record/event/survey/instance
    public static function getConsentIdOfStoredConsentForm($record, $event_id, $survey_id, $instance=1)
    {
        $sql = "select consent_id from redcap_surveys_pdf_archive 
                where record = ? and event_id = ? and survey_id = ? and instance = ?
                order by doc_id desc
                limit 1";
        $q = db_query($sql, [$record, $event_id, $survey_id, $instance]);
        return (db_num_rows($q) > 0 ? db_result($q, 0) : null);
    }

    // Display dialog for user to choose a survey that does not have e-Consent enabled yet
    public function surveySelectDialog()
    {
        global $Proj;
        // Get surveys with e-Consent enabled
        $surveysEnabled = [];
        foreach ($this->getAllEconsents($Proj->project_id) as $attr) {
            $surveysEnabled[$attr['survey_id']] = true;
        }
        // Get survey ids/titles
        $opts = [''=>"-- ".RCView::tt('survey_404','')." --"];
        foreach ($Proj->surveys as $survey_id=>$attr) {
            if (isset($surveysEnabled[$survey_id])) continue;
            $opts[$survey_id] = '"'.strip_tags($attr['title']).'" ('.$attr['form_name'].')';
        }
        $msg = (count($opts) !== 1) ? "" : RCView::div(['class'=>'mt-3 text-dangerrc font-weight-bold'], RCView::fa('fa-solid fa-circle-exclamation mr-1') . RCView::tt('econsent_48'));
        print   RCView::p(['class'=>'mt-0'], RCView::tt('econsent_49')) . $msg .
                RCView::div(['class'=>'mt-3'], RCView::select(['class'=>'x-form-text x-form-field', 'style'=>'max-width:95%;', 'id'=>'enable_for_survey', 'onchange'=>"$('#editSetupPre').dialog('close');openSetupDialog(null,this.value);"], $opts, "", 200));
    }

    // Get Custom e-Consent Label string
    public function getCustomEconsentLabel($project_id, $record, $event_id, $form, $instance, $consent_id, $removeIdentifiers=false)
    {
        $Proj = new Project($project_id);
        $custom_econsent_label = $this->getAllEconsents($project_id)[$consent_id]['custom_econsent_label'] ?? '';
        return trim(Piping::replaceVariablesInLabel($custom_econsent_label, $record, $event_id, $instance, [], false, $project_id, false,
                ($Proj->isRepeatingForm($event_id, $form) ? $form : ""), 1, false, $removeIdentifiers, $form));
    }

}