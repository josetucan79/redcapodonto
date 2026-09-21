<?php namespace MultiLanguageManagement;

/**
 * Outputs the Multi-Language Managment pages (Project, Control Center)
 */

use Crypto, RCIcon, RCView, System, UserRights;

#region PHP code

// Prevent this page from being called directly
if ($_GET["route"] === "MultiLanguageController:systemConfig") {
    require_once dirname(dirname(__FILE__)) . "/Config/init_global.php";
    if (!defined("USERID")) System::redirectHome();
	if (!ACCESS_CONTROL_CENTER) redirect(APP_PATH_WEBROOT);
    $is_project = false;
    $pid = "SYSTEM";
    $endpoint = APP_PATH_WEBROOT . "index.php?context=sys&route=MultiLanguageController:ajax";
}
// Project-context - only show when enabled on system
else if ($_GET["route"] === "MultiLanguageController:projectSetup" && MultiLanguage::isActive()) {
    require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";
    if (!defined("USERID") || !defined("PROJECT_ID")) System::redirectHome();
    $is_project = true;
    $pid = PROJECT_ID;
    $endpoint = APP_PATH_WEBROOT . "index.php?pid={$pid}&context=proj&route=MultiLanguageController:ajax";
}
else {
    System::redirectHome();
}
$userid = USERID;
$clickableMCLangsHTML = MultiLanguage::getMyCapAppLanguageList(true);
$allowedLangsHTML = MultiLanguage::getMyCapAppLanguageList(false);
$concurrent_user = MultiLanguage::checkSimultaneousUsers();

global $ai_mlmtranslator_service_enabled;
if ($concurrent_user === false) {
    // Setup ajax verification
    $crypto = Crypto::init();
    $ajax = array(
        "verification" => $crypto->encrypt(array(
            "random" => $crypto->genKey(),
            "pid" => $pid,
            "user" => $userid,
            "timestamp" => time(),
        )),
        "endpoint" => $endpoint,
        "csrfToken" => System::getCsrfToken(),
    );
    
    // Get settings and languages
    $sys_settings = MultiLanguage::getSystemSettings();
    $proj_meta = array();

    $init_from_scratch = true;
    $init_from_file = true;

    if ($is_project) {
        $proj_settings = MultiLanguage::getProjectSettings($pid, true);
        $project_in_production = $GLOBALS["status"] == 1;
        // Project metadata
        $proj_meta = MultiLanguage::getProjectMetadata($pid);
        // Add project metadata hash to settings to know if something has changed since the setup page has loaded
        $proj_settings["projMetaHash"] = MultiLanguage::getProjectMetadataHash($pid);
        // Designated field candidates
        $designated = MultiLanguage::getDesignatedFieldCandidates($pid);
        // Admin activation required
        $require_admin_activation = MultiLanguage::isAdminActivationRequired();
        $langs_defined = count($proj_settings["langs"]) > 0;
        // UI access in subscribed languages
        // (adding this here because system settings are not needed during non-setup actions)
        $proj_settings["disable-ui-overrides"] = $sys_settings["disable-ui-overrides"];
        $force_subscription = $sys_settings["force-subscription"] && !$proj_settings["optional-subscription"];
        $proj_settings["force-subscription"] = $sys_settings["force-subscription"];
        $proj_settings["pdfLink"] = [
            "url" => APP_PATH_WEBROOT_FULL . "redcap_v" . REDCAP_VERSION . "/index.php?route=PdfController:index&pid=$pid",
            "pageParam" => "page",
            "langParam" => MultiLanguage::LANG_GET_NAME,
            "forceParam" => MultiLanguage::LANG_PDF_FORCE
        ];
        // Admin-set limitations
        $init_from_scratch = !$sys_settings["disable-from-scratch"] || $proj_settings["allow-from-scratch"];
        $init_from_file = !$sys_settings["disable-from-file"] || $proj_settings["allow-from-file"];
        $mycap_enabled = $proj_meta["myCapEnabled"] == "1";
   }
    
    // User Interface Translations
    $ui_subheadings = MultiLanguage::getUISubheadings();
    $ui_categories = MultiLanguage::getUICategories();
    $ui_meta = MultiLanguage::getUIMetadata(!$is_project);
    
    // System Languages
    $sys_langs = MultiLanguage::getSystemLanguages();
    $has_sys_langs = count($sys_langs) > 0;

    addLangToJS(array(
        "datatables_02",
        "datatables_03",
        "datatables_04",
        "datatables_05",
        "datatables_06",
        "datatables_07",
        "datatables_08",
        "datatables_09",
        "datatables_10",
        "datatables_11",
        "data_entry_64",
        "design_196",
        "edit_project_207",
        "global_29",
        "global_30",
        "global_106",
        "global_159",
        "home_30",
        "home_33",
        "home_65",
        "index_09",
        "multilang_04",
        "multilang_05",
        "multilang_06",
        "multilang_34",
        "multilang_58",
        "multilang_59",
        "multilang_66",
        "multilang_67",
        "multilang_76",
        "multilang_77",
        "multilang_78",
        "multilang_83",
        "multilang_103",
        "multilang_107",
        "multilang_138",
        "multilang_150",
        "multilang_151",
        "multilang_164",
        "multilang_165",
        "multilang_166",
        "multilang_167",
        "multilang_168",
        "multilang_170",
        "multilang_176",
        "multilang_185",
        "multilang_202",
        "multilang_204",
        "multilang_219",
        "multilang_565",
        "multilang_567",
        "multilang_572",
        "multilang_594",
        "multilang_595",
        "multilang_598",
        "multilang_599",
        "multilang_600",
        "multilang_570",
        "multilang_601",
        "multilang_602",
        "multilang_603",
        "multilang_604",
        "multilang_605",
        "multilang_609",
        "multilang_610",
        "multilang_611",
        "multilang_612",
        "multilang_613",
        "multilang_614",
        "multilang_620",
        "multilang_621",
        "multilang_637",
        "multilang_639",
        "multilang_640",
        "multilang_641",
        "multilang_642",
        "multilang_643",
        "multilang_644",
        "multilang_664",
        "multilang_665",
        "multilang_666",
        "multilang_667",
        "multilang_689",
        "multilang_697",
        "multilang_698",
        "multilang_711",
        "multilang_734",
        "multilang_783",
        "mycap_mobile_app_02",
        "mycap_mobile_app_101",
        "setup_87",
        "global_48",
        "multilang_808",
    ));

    // Prepare data to be sent to JavaScript
    $js_data = array(
        "ajax" => $ajax,
        "mode" => $is_project ? "Project" : "System",
        "projMeta" => $proj_meta,
        "settings" => $is_project ? $proj_settings : $sys_settings,
        "snapshots" => false, // Lazy loading
        "uiMeta" => $ui_meta,
        "uiSubheadings" => $ui_subheadings,
        "csvDelimiter" => \User::getCsvDelimiter(),
        "sysLangs" => $sys_langs,
    );

    if ($is_project) {
        $js_data['myCapSupportedLanguages'] = array_keys(MultiLanguage::MYCAP_SUPPORTED_LANGS);
    }

    $init_json = MultiLanguage::convertAssocArrayToJSON($js_data);

    if (!$is_project) {
        ?><style type="text/css">#pagecontainer { max-width: 1400px; } </style><?php
    }
}

#endregion

#region Main content (HTML)

#region Concurrent Use

if ($concurrent_user) {
?>
<div class="mlm-setup-container">
    <?php if ($is_project): ?>
    <div class="projhdr"><i class="fas fa-globe"></i> <?= RCView::tt("multilang_01") ?></div>
    <?php else: ?>
    <h4 style="margin-top:0;"><i class="fas fa-globe"></i> <?= RCView::tt("multilang_01") ?></h4>
    <?php endif; ?>
    <div class="yellow my-3" style="max-width: 850px;">
        <div>
            <img src="<?=APP_PATH_IMAGES?>exclamation_orange.png">
            <b><?=RCView::tt("multilang_584")?></b><br><br><?=RCView::tt_i("multilang_585", array(
                "<b>{$concurrent_user["user_id"]}</b> - <a href=\"mailto:{$concurrent_user["email"]}\">{$concurrent_user["full_name"]}</a>"), false)?>
        </div>
        <div id="errconflict" class="brown" style="display:none;margin:10px 0;">
            <?=RCView::tt_i("multilang_586", array($concurrent_user["timer"]))?>
        </div>
        <div style="margin-top:10px;">
            <table role="presentation" style="width:100%;">
                <tr>
                    <td>
                        <button onclick="window.location.reload();return false;"><?=RCView::tt("data_entry_84")?></button>
                    </td>
                    <td style="text-align:right;">
                        <a href="javascript:;" onclick="$(this).remove();$('#errconflict').show('fast');" style="font-size:11px;"><?=RCView::tt("data_entry_85")?></a>
                    </td>
                </tr>
            </table>
        </div>
    </div>
</div>
<?php
}

#endregion

if (!$concurrent_user) {

#region Title and Instructions
?>
<div class="mlm-setup-container">
<?php if ($is_project): ?>
<div class="mlm-setup-title-container">
    <div class="mlm-setup-title">
        <i class="fa-solid fa-globe me-1"></i>
        <?= RCView::tt("multilang_01") ?>
    </div>
    <!-- Manual and Video Tutorial -->
    <div class="mlm-setup-trainingmats d-print-none">
        <div>
            <?=RCIcon::Manual("text-secondary me-1")?>
            <a href="<?=APP_PATH_WEBROOT."/Resources/misc/mlm-guide.pdf"?>" target="_blank"><?= RCView::tt("multilang_806") ?></a>
        </div>
        <div>
            <?=RCIcon::Video("text-secondary me-1")?>
            <a href="javascript:;" onclick="popupvid('mlm01.mp4','<?=RCView::tt_js('multilang_01')?>');"><?= RCView::tt("training_res_109") ?></a>
        </div>
    </div>
</div>
<p id="mlmInstr1">
    <?=RCView::tt("multilang_55") // Use this page to configure multiple display languages for your project (surveys, data entry forms, alerts, ASIs, etc.) and to import/export translation sets. Do not forget to save your changes when you are done editing this page (you can use the keyboard shortcut <span class="badge badge-secondary shortcut" data-bs-toggle="tooltip" title="On Mac, use CMD-S">CTRL-S</span>)! ?> 
    <a id="mlmLearnMore" href="javascript:;" onclick="$(this).hide();$('#mlmInstr2, #mlmInstr3').removeClass('hide');" style="text-decoration:underline;"><?= RCView::tt("multilang_54") // Learn more. ?></a>
</p>
<p id="mlmInstr2" class="hide">
    <?=RCView::tt("multilang_148") // Multi-language support in REDCap works by providing translations for each display item, i.e. field labels, choice labels, and user interface elements, but also for items such as email subjects and body texts of alerts and automated survey invitations. Translations for each supported item can be set on this page. In case an item is not translated, the item will be shown in the language defined as fallback, or, if not set there either, the <i>reference</i> value will be used. ?>
</p>
<p id="mlmInstr3" class="hide">
    <?=RCView::tt("multilang_801")?>
    <a href="javascript:;" onclick="$('#mlmLearnMore').show();$('#mlmInstr2, #mlmInstr3').addClass('hide');" style="text-decoration:underline;"><?= RCView::tt("multilang_53") // Show less. ?></a>
</p>
<?php if ($GLOBALS['status'] > 0) { ?>
    <p id="mlm-draft-mode-notice" class="yellow my-3" style="max-width: 850px;">
        <i class="fas fa-exclamation-circle"></i>
        <?php if ($GLOBALS['draft_mode'] == '1') { ?>
            <?=RCView::b(RCView::tt("design_14")) // Since this project is currently in PRODUCTION, changes will not be made in real time.  ?><br>
            <?=RCView::tt("multilang_222") // Your project is currently in draft mode... ?>
        <?php } else { ?>
            <?=RCView::tt("multilang_221") // Because the project is currently in production status, this page can only be modified while in draft mode... ?>
        <?php } ?>
    </p>
<?php } ?>
<p class="mlm-off-notice red hide"><?=RCView::tt("multilang_573") // Multi-Langauage Management is currently <b>turned off</b> in this project! ?></p>
<?php else: ?>
<h4 style="margin-top:0;"><i class="fas fa-globe"></i> <?= RCView::tt("multilang_01") ?></h4>
<div class="mlm-setup-title-container">
    <!-- Manual -->
    <div class="mlm-setup-trainingmats d-print-none">
        <div>
            
        </div>
    </div>
</div>
<p id="mlmInstr1">
    <?=RCView::tt("multilang_56") // Use this page to configure multiple display languages available on this REDCap instance that are available for projects to use. When used in a project, user interface translations defined here will be copied to the project and can be freely customized there. Do not forget to save your changes when you are done editing this page (you can use the keyboard shortcut <span class="badge badge-secondary shortcut" data-bs-toggle="tooltip" title="On Mac, use CMD-S">CTRL-S</span>)! ?> 
</p>
<p>
    <?=RCView::lang_i("multilang_807", [
        RCIcon::Manual("text-secondary me-1 ms-1")."<a href=\"".APP_PATH_WEBROOT."Resources/misc/mlm-manual.pdf\" target=\"_blank\">",
        "</a>"
    ], false)?>
</p>

<p class="mlm-off-notice red hide"><?=RCView::tt("multilang_65") // Multi-Langauage Management is currently <b>turned off</b> system-wide. In all projects, surveys and data entry forms are currently <b>not</b> translated and the Multi-Language Mangagement menu is not visible. ?></p>
<?php endif; ?>
<p class="mlm-hash-mismatch-warning yellow hide">
    <i class="fas fa-exclamation-triangle fa-2x text-danger"></i>
    <?=RCView::tt("multilang_104") // <b>ATTENTION:</b> The underlying project data has changed since this page was loaded. It is suggested that you save any unsaved changes and then reload this page before you make any further changes. ?>
</p>
<div class="mlm-items-hash-changed-warning yellow hide mb-4">
    <i class="fas fa-exclamation-triangle text-danger"></i>
    <?=RCView::tt("multilang_564") // <b>ATTENTION:</b> The original values of some translated items have changed, and thus some translations might be out of date. ?>
    <a href="javascript:;" data-mlm-action="review-changed-hash-items" style="text-decoration:underline;" data-mlm-review-hash-changed-items>multilang_565</a>
</div>
<p data-mlm-loading>
    <i class="fas fa-spinner fa-spin"></i> <?=RCView::tt("multilang_52") // Initializing ... ?>
</p>
<?php
#endregion

#region Sub-Navigation
?>
<div data-mlm-initialized id="sub-nav" class="d-sm-block" style="display:none !important;margin-bottom:0.5em !important;">
    <ul>
        <li class="active">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="languages" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-globe"></i> <?=RCView::tt("multilang_67") // Languages ?></a>
        </li>
        <?php if ($is_project): ?>
        <li class="hidden-when-no-langs">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="forms" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-table"></i> <?=RCView::tt("multilang_68") // Forms/Surveys ?></a>
        </li>
        <?php if ($mycap_enabled): ?>
        <li class="hidden-when-no-langs">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="mycap" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fa-solid fa-mobile-screen-button"></i> <?=RCView::tt("mycap_mobile_app_101") // MyCap ?></a>
        </li>
        <?php endif; // mycap_enabled ?>
        <li class="hidden-when-no-langs">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="alerts" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-bell"></i> <?=RCView::tt("multilang_69") // Alerts ?></a>
        </li>
        <li class="hidden-when-no-langs">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="misc" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-random"></i> <?=RCView::tt("multilang_70") // Misc ?></a>
        </li>
        <?php endif; ?>
        <li class="hidden-when-no-langs">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="ui" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-desktop"></i> <?=RCView::tt("multilang_71") // User Interface ?></a>
        </li>
        <?php if (false && !$is_project): ?>
        <li class="hidden-when-no-langs">
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="defaults" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-align-left"></i> <?=RCView::tt("multilang_141") // Defaults ?></a>
        </li>
        <?php endif; ?>
        <?php if (!$is_project): ?>
        <li>
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="usage" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-chart-line"></i> <?=RCView::tt("multilang_635") // Statistics ?></a>
        </li>
        <?php endif; ?>
        <li>
            <a href="javascript:;" data-mlm-action="main-nav" data-mlm-target="settings" style="font-size:13px;color:#393733;padding:7px 9px;"><i class="fas fa-cog"></i> <?=RCView::tt("multilang_72") // Settings ?></a>
        </li>
    </ul>
    <div style="font-weight:normal;display:inline-block;margin-left:10px;padding-top:3px;padding-bottom:3px;">
        &mdash;
        <button data-mlm-action="save-changes" class="btn btn-light btn-xs">
            <i class="fas fa-save"></i> &nbsp; <?=RCView::tt("report_builder_28") // Save Changes ?>
        </button>
    </div>
</div>
<div data-mlm-initialized class="mlm-tabs">
<?php
#endregion

#region Languages Tab
?>
<div data-mlm-tab="languages" class="d-none">
    <?php if ($is_project): // Project ?>
    <?php if (count($proj_settings["langs"])): ?>
    <p id="mlm-initial-help-toggle">
        <?=RCView::tt("multilang_704") ?>
        <a href="javascript:" onclick="$('#mlm-initial-help').show();$('#mlm-initial-help-toggle').hide();">
            <i class="fa-solid fa-circle-info ms-1"></i>
            <?=RCView::tt("multilang_54") ?>
        </a>
    </p>
    <?php endif; ?>
    <div id="mlm-initial-help" style="<?=count($proj_settings["langs"]) ? "display:none;" : ""?>">
        <p>
            <?=RCView::tt("multilang_236") ?>
        </p>
        <ol class="my-0">
            <li><?=RCView::tt("multilang_699") ?></li>
            <li><?=RCView::tt("multilang_700") ?></li>
            <li><?=RCView::tt("multilang_701") ?></li>
            <li><?=RCView::tt("multilang_702") ?></li>
            <li><?=RCView::tt("multilang_241") ?>
            <li><?=RCView::tt("multilang_240") ?><button class='btn btn-xs btn-rcred btn-rcred-light ms-2' data-mlm-action="explain-actiontags" style='line-height: 14px;padding:1px 3px;font-size:11px;margin-right:6px;'>@ <?=RCView::tt("global_132")?></button></li>
        </ol>
    </div>
    <?php else: // Control Center ?>
    <p>
        <?=RCView::tt("multilang_798")?>
    </p>
    <?php endif; ?>
    <p>
        <button data-mlm-action="add-language" class="btn btn-rcgreen btn-xs fs13 my-2">
            <i class="fas fa-plus"></i> <?=RCView::tt("multilang_07") // Add a new language ?>
        </button>
        <span class="ms-2 hidden-when-no-langs remove-when-control-center">
            <?= RCView::tt("multilang_606") // Export or import general settings ?><a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_610 + '<br><br>' + window.lang.multilang_611, window.lang.multilang_609, null, 400);">?</a>
            :
            <button data-mlm-action="export-general" class="btn btn-light btn-sm text-primary" title="<?=RCView::tt_js2('multilang_607')?>" data-bs-toggle="tooltip"><i class="fas fa-file-download"></i></button>
            <button data-mlm-action="import-general" class="btn btn-light btn-sm" title="<?=RCView::tt_js2('multilang_608')?>" data-bs-toggle="tooltip"><i class="fas fa-file-upload"></i></button>
        </span>
    </p>
    <p class="mlm-no-languages">
        <?=$is_project 
            ? RCView::tt("multilang_09") // Currently, there are no languages set up in this project.
            : RCView::tt("multilang_10") // Currently, there are no system languages configured.?>
    </p>
    <?php if ($is_project): // LANGUAGES TABLE (Project) ?>
    <div id="mlm-languages">
        <table class="table table-responsive table-md mlm-fit-content">
            <thead>
                <tr class="mlm-sticky-header">
                    <th scope="col"><?=RCView::tt("multilang_73") // ID ?></th>
                    <th scope="col" class="text-start"><?=RCView::tt("multilang_25") // Display Name ?></th>
                    <th scope="col" class="text-start"><?=RCView::tt("global_27") // Edit ?></th>
                    <th scope="col" data-bs-toggle="tooltip" 
                        title="<?=RCView::tt_attr("multilang_735") // Check to enable this language ?>">
                        <?=RCView::tt("setup_87") // Active ?>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_697") // Base Language ?> <a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_698, window.lang.multilang_697, null, 400);">?</a>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_76") // Fallback ?> <a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_204, window.lang.multilang_76, null, 400);">?</a>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_77") // RTL ?> <a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_202, window.lang.multilang_77, null, 400);">?</a>
                    </th>
                    <?php if ($mycap_enabled): ?>
                    <th scope="col">
                        <?=RCView::tt("mycap_mobile_app_101") // MyCap ?> <a class="help fs10" href="javascript:;" onclick="simpleDialog(null, null, 'myCapLangsDialog', 500);">?</a>
                    </th>
                    <?php endif; // $mycap_enabled ?>
                    <th scope="col" class="text-start"><?=RCView::tt("control_center_4540") // Actions ?></th>
                </tr>
            </thead>
            <tbody id="mlm-languages-rows">
            </tbody>
        </table>
    </div>
    <template data-mlm-template="languages-row">
        <tr data-mlm-language="">
            <th scope="row">
                <div class="mlm-text-cell">
                    <span data-mlm-config="key"></span>
                </div>
            </th>
            <td>
                <div class="mlm-text-cell">
                    <span data-mlm-config="display"></span>
                </div>
            </td>
            <td>
                <button data-mlm-action="edit-language" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_20')?>">
                    <i class="fa-solid fa-pencil"></i>
                </button>
                <button data-mlm-action="update-language" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_683')?>">
                    <i class="fa-solid fa-circle-chevron-up"></i>
                </button>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <span class="switch switch-xs">
                        <input type="checkbox" class="switch" data-mlm-config="active" name="active" id="">
                        <label data-mlm-config="active" for=""></label>
                    </span>
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input data-mlm-config="refLang" type="radio" name="refLang">
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input data-mlm-config="fallbackLang" type="radio" name="fallbackLang">
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input type="checkbox" class="switch" data-mlm-config="rtl" name="rtl">
                </div>
            </td>
            <?php if ($mycap_enabled): ?>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input type="checkbox" class="switch" data-mlm-config="mycap-active" name="mycap-active">
                </div>
            </td>
            <?php endif; // $mycap_enabled ?>
            <td>
                <button data-mlm-action="toggle-actions" class="btn btn-light btn-sm">
                    <i class="fa-solid fa-ellipsis"></i>
                </button>
                <button data-mlm-action="export-language" class="btn btn-light btn-sm text-primary" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_97')?>">
                    <i class="fas fa-file-download"></i>
                </button>
                <button data-mlm-action="empty-pdf-all" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('design_266')?>">
                    <i class="fa-solid fa-file-pdf"></i>
                </button>
                <template class="language-actions">
                    <button data-mlm-action="translate-forms" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_68')?>"><i class="fas fa-table"></i></button>
                    <?php if ($mycap_enabled): ?>
                    <button data-mlm-action="translate-mycap" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('mycap_mobile_app_101')?>"><i class="fa-solid fa-mobile-screen-button"></i></button>
                    <?php endif; // $mycap_enabled ?>
                    <button data-mlm-action="translate-alerts" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_69')?>"><i class="fas fa-bell"></i></button>
                    <button data-mlm-action="translate-misc" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_70')?>"><i class="fas fa-random"></i></button>
                    <button data-mlm-action="translate-ui" class="btn btn-light btn-sm mlm-stack" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_71')?>"><i class="fa-solid fa-desktop"></i><i class="fa-solid fa-bolt-lightning text-warning"></i></button>
                    |
                    <button data-mlm-action="delete-language" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_688')?>"><i class="far fa-trash-alt text-danger"></i></button>
                </template>
            </td>
        </tr>
    </template>
    <p class="mt-5 boldish fs14 text-dangerrc">
        <?=RCView::tt("multilang_794") // (Optional) Designate a field for storing language preference ?>
    </p>
    <p><?=RCView::tt("multilang_795")?></p>
    <div class="form-group form-group-sm mlm-designated-field-block">
        <label for="designated-language-field" class="col-12-sm control-label d-inline-block me-2 text-dangerrc">
            <i class="fas fa-language fs16 align-middle me-1"></i>
            <?=RCView::tt("multilang_121") // Language preference field: ?>
        </label>
        <select id="designated-language-field" data-mlm-config="designatedField" class="d-inline x-form-text x-form-field" style="width: auto;">
            <option value=""><?=RCView::tt_attr("multilang_122")?></option>
            <?php foreach ($designated as $line) { print $line["html"]; } ?>
        </select>
        <p class="mlm-designated-field-warning hide">
            <i class="fas fa-exclamation-circle text-danger"></i> 
            <?=RCView::tt("multilang_124") // <b>NOTE:</b> The field's options do not include all active languages! ?>
        </p>
    </div>
    <p><?=RCView::tt("multilang_201")?></p>
    <p class="cc_info text-secondary">
        <?=RCView::tt("multilang_123") // <b>NOTE:</b> Similar to a designated email field, when this field exists on multiple events in longitudinal projects, on a repeating instrument, or on a repeating event, the field's value will be syncronized across all instances/events so that changing it in one location will change the value across all events/instances where the field appears. ?>
    </p>
    <hr class="mt-4">
    <div class="ms-3 mt-3">
        <button disabled data-mlm-action="create-snapshot" class="btn btn-xs btn-defaultrc"><i class="fas fa-spinner fa-spin me-1 when-disabled hide"></i><i class="fas fa-camera me-1 when-enabled"></i> <?=RCView::tt("multilang_160") // Create Snapshot ?></button>
    </div>
    <p>
        <?=RCView::tt("multilang_162") // A snapshot of your current translation settings (all translations and settings for all languages) can be saved and stored by simply clicking the 'Create Snapshot' button on this page. All snapshots can be accessed and downloaded at any time from the table below (click the 'Show/Hide Snapshots' link to toggle display of the table). There is no limit to how many snapshots can be created. Creating a snapshot can be useful to allow you to revert your translations back to a specific point in time, if desired, by downloading the snapshot (a ZIP file) and re-importing the individual files (one per language) contained in it. Note that restoring this way will be based on a <i>best effort</i> scheme, as the underlying project structure may have changed since the snapshot was created. ?>
        <br>
        <?=RCView::tt("multilang_682") // Note, when creating a snapshot, it will always reflect the currently saved state. Thus, any unsaved changes will not be included in the snapshot. ?>
        <?=RCView::tt("multilang_791") // Furthermore, when the project status is PRODUCTION, snapshots will not contain any drafted changes. ?>
        <?=RCView::tt("multilang_793") // However, each time draft changes are approved, or when initially moving to production, a snapshot is created automatically. ?>
    </p>
    <div class="ms-3 mt-2">
        <a data-mlm-action="toggle-snapshots" class="me-2" style="cursor:pointer;"><?=RCView::tt("multilang_161") // Show/Hide Snapshot ?></a>
        <span style="display:inline-block">(</span>
        <div class="mlm-inline-checkbox">
            <input data-mlm-action="toggle-show-deleted-snapshots" type="checkbox" id="mlm-show-deleted-snapshots">
          <label for="mlm-show-deleted-snapshots"><?=RCView::tt("multilang_169") // Show deleted snapshots ?></label>
        </div>
        )
    </div>
    <div class="mlm-snapshots-table hide">
        <table class="table table-responsive table-md">
            <thead>
                <tr>
                    <th scope="col"><?=RCView::ttfy("Timestamp") // Timestamp ?></th>
                    <th scope="col"><?=RCView::tt("rev_history_14") // Created by ?></th>
                    <th scope="col"><?=RCView::tt("control_center_4540") // Actions ?></th>
                </tr>
            </thead>
            <tbody>
            </tbody>
        </table>
    </div>
    <?php else: // LANGUAGES TABLE (System) ?>
    <div id="mlm-languages">
        <table class="table table-responsive table-md mlm-fit-content">
            <thead>
                <tr class="mlm-sticky-header-cc">
                    <th scope="col"><?=RCView::tt("multilang_73") // ID ?></th>
                    <th scope="col" class="text-start"><?=RCView::tt("multilang_25") // Display Name ?></th>
                    <th scope="col" class="text-start"><?=RCView::tt("global_27") // Edit ?></th>
                    <th scope="col" class="text-end">%</th>
                    <th scope="col">
                        <?=RCView::tt("setup_87") // Active 
                        ?><a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_605, window.lang.setup_87, null, 400);">?</a>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_78") // Visible 
                        ?><a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_604, window.lang.multilang_78, null, 400);">?</a>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_697") // Base Language 
                        ?><a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_603, window.lang.multilang_697, null, 400);">?</a>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_601") // Initial 
                        ?><a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_602, window.lang.multilang_601, null, 400);">?</a>
                    </th>
                    <th scope="col">
                        <?=RCView::tt("multilang_77") // RTL 
                        ?><a class="help fs10" href="javascript:;" onclick="simpleDialog(window.lang.multilang_202, window.lang.multilang_77, null, 400);">?</a>
                    </th>
                    <th scope="col" class="text-start"><?=RCView::tt("control_center_4540") // Actions ?></th>
                </tr>
            </thead>
            <tbody id="mlm-languages-rows">
            </tbody>
        </table>
    </div>
    <template data-mlm-template="languages-row">
        <tr data-mlm-language="">
            <th scope="row">
                <div class="mlm-text-cell">
                    <span data-mlm-config="key"></span>
                </div>
            </th>
            <td>
                <div class="mlm-text-cell">
                    <span data-mlm-config="display"></span>
                </div>
            </td>
            <td>
                <button data-mlm-action="edit-language" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_20')?>">
                    <i class="fa-solid fa-pencil"></i>
                </button>
                <button data-mlm-action="update-language" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_683')?>">
                    <i class="fa-solid fa-circle-chevron-up"></i>
                </button>
            </td>
            <td>
                <div class="mlm-text-cell text-end">
                    <span data-mlm-config="percent"></span>
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-toggle-cell">
                    <span class="switch switch-xs">
                        <input type="checkbox" class="switch" data-mlm-config="active" name="active" id="">
                        <label data-mlm-config="active" for=""></label>
                    </span>
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-toggle-cell">
                    <span class="switch switch-xs">
                        <input type="checkbox" class="switch" data-mlm-config="visible" name="visible" id="">
                        <label data-mlm-config="visible" for=""></label>
                    </span>
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input data-mlm-config="refLang" type="radio" name="refLang">
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input data-mlm-config="initialLang" type="radio" name="initialLang">
                </div>
            </td>
            <td class="text-center">
                <div class="mlm-radio-cell">
                    <input type="checkbox" class="switch" data-mlm-config="rtl" name="rtl">
                </div>
            </td>
            <td>
                <button data-mlm-action="translate-ui" class="btn btn-light btn-sm" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_71')?>"><i class="fas fa-desktop"></i></button>
                <button data-mlm-action="export-language" class="btn btn-light btn-sm text-primary" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_97')?>"><i class="fas fa-file-download"></i></button>
                |
                <div class="mlm-disabled-tooltip-wrapper">
                    <button data-mlm-action="delete-language" class="btn btn-light btn-sm mlm-stack" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_688')?>"><i class="far fa-trash-alt text-danger"></i><i class="fa-solid fa-bolt-lightning text-warning"></i></button>
                </div>
            </td>
        </tr>
    </template>
    <?php endif; ?>
</div>
<?php
#endregion 

if ($is_project):

#region Forms, Fields, Survey Settings, ASIs
?>
<div data-mlm-tab="forms" class="d-none">
    <!-- Language Switcher -->
    <div class="mlm-language-switcher">
        <div class="mlm-switcher-buttons"></div>
    </div>
    <p class="mlm-reflang-notice yellow"><?=RCView::tt("multilang_797")?></p>
    <div id="mlm-forms" data-mlm-mode="table">
        <p><?=RCView::tt("multilang_799")?></p>
        <p class="remove-when-surveys-off hide-when-ref-lang" data-mlm-sq-item="sq-survey_queue_custom_text">
            <button href="javascript:;" data-mlm-indicator data-mlm-action="translate-surveyqueue" class="btn btn-light btn-xs"><i class="fas fa-edit"></i> <?=RCView::tt("multilang_58") // Custom Survey Queue Text ?></button>
            <span class="mlm-ref-changed-icon text-danger" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_146")?>" data-rc-lang-attrs="title=multilang_146" data-mlm-ref-changed><i class="fas fa-exclamation-circle"></i></span>
        </p>
        <p class="remove-when-surveys-off hide-when-ref-lang" data-mlm-sq-item="sq-survey_auth_custom_message">
            <button href="javascript:;" data-mlm-indicator data-mlm-action="translate-surveylogin" class="btn btn-light btn-xs"><i class="fas fa-edit"></i> <?=RCView::tt("multilang_59") // Custom Survey Login Error Message ?></button>
            <span class="mlm-ref-changed-icon text-danger" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_146")?>" data-rc-lang-attrs="title=multilang_146" data-mlm-ref-changed><i class="fas fa-exclamation-circle"></i></span>
        </p>
        <!-- Instruments Table -->
        <table class="table table-responsive table-md mlm-fit-content">
            <thead>
                <tr class="mlm-sticky-header">
                    <th scope="col"><?=RCView::tt("global_89") // Instrument ?></th>
                    <th scope="col" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_736") // Check to enable translations on data entry instruments ?>"><?=RCView::tt("bottom_20") // Data Entry ?></th>
                    <th scope="col" class="remove-when-surveys-off" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_737") // Check to enable translations surveys ?>"><?=RCView::tt("survey_437") // Survey ?></th>
                    <th scope="col"><?=RCView::tt($mycap_enabled ? "multilang_732" : "home_32") // Fields/Tasks | Fields ?></th>
                    <th class="remove-when-surveys-off" scope="col"><?=RCView::tt("multilang_63") // Survey Settings ?></th>
                    <th class="remove-when-surveys-off" scope="col"><span class="hide-when-ref-lang"><?=RCView::tt("multilang_79") // ASIs ?></span><span class="show-when-ref-lang"><?=RCView::tt("multilang_650") // ASI Langauge Source ?></span></th>
                    <th scope="col" data-bs-toggle="tooltip" title="<?=RCView::tt_attr('multilang_738')?>">
                        <?=RCView::tt("global_71") // Export ?>
                    </th>
                </tr>
            </thead>
            <tbody id="mlm-forms-rows">
            </tbody>
        </table>
        <?php if($GLOBALS["google_recaptcha_site_key"] != "" && $GLOBALS["google_recaptcha_secret_key"] != ""): ?>
        <p class="remove-when-surveys-off">
            <?=RCView::tt_i("multilang_695", [
                "<a href=\"https://developers.google.com/recaptcha/docs/language\" target=\"_blank\" style=\"text-decoration:underline;\">",
                "</a>"
            ], false) // Google reCAPTCHA is enabled ... ?>
            <br>
            <div class="row ms-2">
                <label for="recaptcha-language-code" class="col-auto col-form-label"><?=RCView::tt("multilang_693") // reCAPTCHA language code value: ?></label>
                <div class="col-sm-2">
                    <input id="recaptcha-language-code" class="form-control form-control-sm" type="text" data-mlm-type="recaptcha-lang" placeholder="<?=RCView::tt_attr("multilang_694") // e.g., en ?>">
                </div>
            </div>
        </p>
        <?php endif; ?>
        <p class="remove-when-surveys-off">
            <i class="fa-regular fa-lightbulb text-warning"></i> 
            <?=RCView::tt_i("multilang_707", [
                MultiLanguage::LANG_SURVEY_URL_OVERRIDE,
                APP_PATH_SURVEY_FULL . "?s=SURVEYHASH&" . MultiLanguage::LANG_SURVEY_URL_OVERRIDE . "=es"
            ], false) // Tip: It is possible to set the initially displayed language of a survey ... ?>
        </p>
        <p class="remove-when-surveys-off">
            <i class="fa-regular fa-lightbulb text-warning"></i>
            <?=RCView::tt("multilang_711") // Tip: Choose your "ASI Language Source" wisely - If using ASIs ... ?>
        </p>
   </div>

    <div id="mlm-fields" data-mlm-mode="fields">
        <h3>
            <?=RCView::tt("home_32", "span", ["class" => "hide-when-mycap-task"]) // Fields ?>
            <?=RCView::tt("multilang_732", "span", ["class" => "hide-when-not-mycap-task"]) // Tasks/Fields ?>
            <span data-mlm-form class="mlm-formname">
                <?=RCView::tt("design_493") // Instrument: ?> <b data-mlm-display="form-display"></b>
                <span class="mlm-survey-settings-link">&ndash; <button data-mlm-action="translate-survey" class="btn btn-link btn-xs"><?=RCView::tt("multilang_63") // Survey Settings ?></button></span>
                <span class="mlm-asis-link">| <button data-mlm-action="translate-asis" class="btn btn-link btn-xs"><?=RCView::tt("multilang_79") // ASIs ?></button></span>
            </span>
        </h3>
        <p data-mlm-promis class="red"><?=RCView::tt("multilang_590") // <b>This is a PROMIS adaptative or auto-scoring instrument</b>. PROMIS instruments are validated for their specific language and should never be translated. Therefore, translation has been disabled for this instrument. ?></p>
        <p data-mlm-fromsharedlibrary class="yellow"><?=RCView::tt("multilang_591") // <b>This instrument has been downloaded from the REDCap Shared Library</b>. Care must be taken when translating curated instruments. Please check if this is even allowed or if there is a version of this instrument that has already been validated for the target language. ?></p>
        <div data-mlm-render="fields"><!-- Fields (rendered in JS) --></div>
        <p data-mlm-no-fields class="yellow"><?=RCView::tt("multilang_61") // There are no translatable fields on this form. They may have been excluded from translation. ?></p>
    </div>

    <div id="mlm-survey" data-mlm-mode="survey">
        <h3 class="mb-4">
            <?=RCView::tt("multilang_63") // Survey Settings ?>
            <span data-mlm-form class="mlm-formname">
                <?=RCView::tt("design_493") // Instrument: ?> <b data-mlm-display="form-display"></b>
                <span class="mlm-fields-link">&ndash; <button data-mlm-action="translate-fields" class="btn btn-link btn-xs nowrap"><?=RCView::tt("home_32") // Fields ?></button></span>
                <span class="mlm-asis-link">| <button data-mlm-action="translate-asis" class="btn btn-link btn-xs nowrap"><?=RCView::tt("multilang_79") // ASIs ?></button></span>
            </span>
        </h3>
        <!-- AI Translation Tool -->
        <?php
        if ($ai_mlmtranslator_service_enabled) {
            print MultiLanguage::getAITranslatorActionHTML();
        }
        ?>
        <div data-mlm-render="survey"><!-- Survey Settings (rendered in JS) --></div>
    </div>

    <div id="mlm-survey" data-mlm-mode="asi">
        <h3 class="mb-4">
            <?=RCView::tt("multilang_79") // ASIs ?>
            <span data-mlm-form class="mlm-formname">
                <?=RCView::tt("design_493") // Instrument: ?> <b data-mlm-display="form-display"></b>
                <span class="mlm-survey-settings-link">&ndash; <button data-mlm-action="translate-fields" class="btn btn-link btn-xs nowrap"><?=RCView::tt("home_32") // Fields ?></button></span>
                <span class="mlm-survey-settings-link">| <button data-mlm-action="translate-survey" class="btn btn-link btn-xs nowrap"><?=RCView::tt("multilang_63") // Survey Settings ?></button></span>
            </span>
        </h3>
        <!-- AI Translation Tool -->
        <?php
        if ($ai_mlmtranslator_service_enabled) {
            print MultiLanguage::getAITranslatorActionHTML();
        }
        ?>
        <div data-mlm-render="asi"><!-- Survey Settings (rendered in JS) --></div>
    </div>

    <div class="d-none"><!-- Templates -->
        <!--#region Forms/Surveys Table Row Template -->
        <template data-mlm-template="forms-row">
            <tr data-mlm-form="" data-mlm-language="">
                <th scope="row">
                    <div class="mlm-text-cell">
                        <!--
                        <button data-mlm-indicator="form-name" data-mlm-ref-changed="form-name" href="javascript:;" data-mlm-action="translate-formname" class="btn btn-light btn-xs disable-when-ref-lang" data-bs-toggle="tooltip" data-placement="top" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_80") // Translate the instrument's name ?>"><i class="fas fa-edit"></i></button>
                        -->
                        <span data-mlm-display="form"></span>
                        <img class="mlm-mycap-task-indicator hide" src="<?=APP_PATH_IMAGES?>mycap_logo_black.png" alt="MyCap" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("mycap_mobile_app_100") // Enabled as MyCap task ?>">
                    </div>
                </th>
                <td class="text-center">
                    <div class="mlm-text-cell text-center">
                        <span class="switch switch-xs" data-mlm-switch="form-active">
                            <input type="checkbox" class="switch" id="" data-mlm-type="form-active" data-mlm-name>
                            <label for="" ></label>
                        </span>
                    </div>
                </td>
                <td class="remove-when-surveys-off">
                    <div class="mlm-text-cell text-center">
                        <span class="switch switch-xs" data-mlm-switch="survey-active">
                            <input type="checkbox" class="switch" id="" data-mlm-type="survey-active" data-mlm-name>
                            <label for=""></label>
                        </span>
                    </div>
                </td>
                <td>
                    <button href="javascript:;" data-mlm-action="translate-fields" class="btn btn-light btn-xs nowrap"><i class="fas fa-edit"></i> <?=RCView::tt("multilang_83") // Translate ?></button>
                </td>
                <td class="remove-when-surveys-off">
                    <div class="mlm-text-cell">
                        <span class="remove-when-survey">&mdash;</span>
                        <button href="javascript:;" data-mlm-action="translate-survey" class="btn btn-light btn-xs nowrap remove-when-not-survey"><i class="fas fa-edit"></i> <?=RCView::tt("multilang_83") // Translate ?></button>
                    </div>
                </td>
                <td class="remove-when-surveys-off">
                    <div class="mlm-text-cell">
                        <span class="remove-when-asis">&mdash;</span>
                        <button href="javascript:;" data-mlm-action="translate-asis" class="btn btn-light btn-xs nowrap remove-when-no-asis hide-when-ref-lang"><i class="fas fa-edit"></i> <?=RCView::tt("multilang_83") // Translate ?></button>
                        <div class="mlm-select-cell remove-when-no-asis show-when-ref-lang">
                            <select data-mlm-type="asi-source" data-mlm-name>
                                <option value="field"><?=RCView::tt_attr("multilang_209") // Language preference field ?></option>
                                <option value="user" selected><?=RCView::tt_attr("multilang_210") // User's or survey respondent's active language ?></option>
                            </select>
                        </div>
                    </div>
                </td>
                <td class="text-center">
                    <button data-mlm-action="export-single-form" class="btn btn-light btn-xs text-primary hide-when-ref-lang" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('multilang_804')?>">
                        <i class="fa-solid fa-file-arrow-down"></i>
                    </button>
                    <button data-mlm-action="empty-pdf" class="btn btn-light btn-xs" data-bs-toggle="tooltip" title="<?=RCView::tt_js2('data_export_tool_158')?>">
                        <i class="fa-solid fa-file-pdf"></i>
                    </button>
                </td>
            </tr>
        </template>
        <!--#endregion-->
        <!--#region Fields Templates -->
        <template data-mlm-template="field-exclusion-table">
            <table class="table table-responsive table-md">
                <thead>
                    <tr>
                        <th scope="col">#</th>
                        <th scope="col"><?=RCView::tt("data_import_tool_98") // Field Name ?></th>
                        <th scope="col"><?=RCView::tt("multilang_84") // Excluded ?></th>
                    </tr>
                </thead>
                <tbody data-mlm-form></tbody>
            </table>
        </template>
        <template data-mlm-template="field-exclusion-row">
            <tr data-mlm-field data-mlm-language>
                <th scope="row">
                    <div class="mlm-text-cell">
                        <span data-mlm-display="rowNumber"></span>
                    </div>
                </th>
                <td>
                    <div class="mlm-text-cell">
                        <span data-mlm-display="fieldName"></span>
                    </div>
                </td>
                <td>
                    <div class="mlm-radio-cell">
                        <input data-mlm-type="field-excluded" type="checkbox" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_85") // Check to exclude this field from being translated ?>">
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="field-jumplist">
            <div class="mlm-field-jumplist">
                <div class="mlm-search-tool">
                    <label for="field-jumplist" class="me-2">
                        <?=RCView::tt("multilang_05") // CTRL-G - Go to field: ?>
                    </label>
                    <select id="field-jumplist" data-mlm-type="fields-jumplist" class="form-control form-control-sm mlm-jumplist ms-2">
                        <option></option>
                    </select>
                    <input class="form-check-input" type="checkbox" data-mlm-action="toggle-hide-fielditems-translated" name="ui-hide-translated-fields" id="ui-hide-translated-fields">
                    <label for="ui-hide-translated-fields" class="ms-1">
                        <?=RCView::tt("multilang_51") // Hide translated items ?> 
                    </label>
                    <button class="btn btn-sm btn-link ms-1" data-mlm-action="refresh-hide-fielditems-translated">
                        <i class="fas fa-sync-alt"></i>
                    </button>
                </div>
                <div class="text-secondary">
                    <?=RCView::tt("multilang_207") // You may check the checkbox for fields whose text does not require translating. ?>
                </div>
            </div>
            <!-- AI Translation Tool -->
            <?php
            if ($ai_mlmtranslator_service_enabled) {
                print MultiLanguage::getAITranslatorActionHTML();
            }
            ?>
        </template>
        <template data-mlm-template="field">
            <div data-mlm-field class="mlm-field-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="translated" class="badge badge-light" data-mlm-display="fieldNum"></span>
                    <div style="display: inline-block;">
                        <input data-mlm-translation data-mlm-type="field-complete" style="position:relative;top:2.5px;margin-left:3px;" type="checkbox" id="" value="1" data-bs-toggle="tooltip" data-bs-trigger="hover" title="<?=RCView::tt_attr("multilang_86") // Check to mark this field as translated ?>">
                    </div>
                    <label class="mlm-field-name" data-mlm-display="fieldName" for=""></label>
                    <span class="mlm-matrix-name" hidden>&ndash; (<span data-mlm-display="matrixName"></span>)</span>
                    <a href="javascript:;" data-mlm-action="reveal-field-items" class="mlm-shown-when-hidden-items"><i class="fa-solid fa-eye"></i></a>
                </div>
                <div class="mlm-field-items">
                    <!-- Items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="task">
            <div data-mlm-field data-mlm-task class="mlm-field-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="translated" class="badge badge-light">T</span>
                    <div style="display: inline-block;">
                        <input data-mlm-translation data-mlm-type="task-complete" style="position:relative;top:2.5px;margin-left:3px;" type="checkbox" id="" value="1" data-bs-toggle="tooltip" data-bs-trigger="hover" title="<?=RCView::tt_attr("multilang_739") // Check to mark the task items as translated ?>">
                    </div>
                    <img class="mlm-mycap-task-indicator" src="<?=APP_PATH_IMAGES?>mycap_logo_black.png" alt="MyCap">
                    <label class="mlm-field-name" data-mlm-task-label for="">
                        <?=RCView::tt("multilang_740") // Task: ?> <span data-mlm-display="taskType"></span>
                    </label>
                    <a href="javascript:;" data-mlm-action="reveal-field-items" class="mlm-shown-when-hidden-items"><i class="fa-solid fa-eye"></i></a>
                </div>
                <div class="mlm-task-items">
                    <!-- Task items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="event-task-items">
            <div data-mlm-event-tasks class="mlm-event-task-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="event-task-translated" class="badge badge-light">&nbsp;</span>
                    <label class="mlm-field-name" data-mlm-display="event-name" for=""></label> (<span data-mlm-display="unique-event-name"></span>)
                </div>
                <div class="mlm-event-task-items">
                    <!-- Items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="task-fields-separator">
            <div class="mlm-task-fields-separator yellow">
                <?=RCView::tt("multilang_748") // Note, since this instrument is a MyCap Task ... ?><br>
                <button type="button" class="btn btn-xs btn-defaultrc mt-2" data-mlm-action="reveal-task-items"><i class="fas fa-eye me-1"></i><?=RCView::tt("multilang_749") // Reveal Task Fields ?></button>
            </div>
        </template>
        <template data-mlm-template="matrix">
            <div data-mlm-matrix class="mlm-field-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="translated" class="badge badge-light">#</span>
                    <div style="display: inline-block;">
                        <input data-mlm-translation data-mlm-type="matrix-complete" style="position:relative;top:2.5px;margin-left:3px;" type="checkbox" id="" value="1" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_87") // Check to mark this matrix as translated ?>">
                    </div>
                    <label class="mlm-field-name" for=""><i style="font-weight: normal;"><?=RCView::tt("design_300") // Matrix group name: ?></i>
                        <span data-mlm-display="matrixName"></span>
                    </label>
                </div>
                <div class="mlm-field-items">
                    <!-- Items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="field-item-text">
            <div data-mlm-field-item>
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="field-item-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <input type="text" data-mlm-translation data-mlm-type data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea" id="" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                </div>
            </div>
        </template>
        <template data-mlm-template="field-item-textarea">
            <div data-mlm-field-item>
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="field-item-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <div class="mlm-with-rte">
                        <textarea data-mlm-translation data-mlm-type data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea textarea-autosize" id="" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                        <button data-mlm-action="rich-text-editor" data-mlm-rtemode="inverted" data-mlm-name data-mlm-type data-mlm-index="" class="btn btn-xs btn-outline-secondary mlm-rte-button"><?=RCView::tt("multilang_90") // Rich Text Editor ?></button>
                    </div>
                </div>
            </div>
        </template>
        <template data-mlm-template="field-item-table">
            <div data-mlm-field-item>
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="field-item-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <table class="table table-responsive table-md mt-2 mlm-choices-table" style="width: 90%;height:auto;">
                        <thead>
                            <tr>
                                <th scope="col" style="width:40px;"></th>
                                <th scope="col" style="width:min-content;" data-mlm-display="choiceType"></th>
                                <th scope="col" style="width:100%;"><?=RCView::tt("multilang_91") // Translation ?></th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </template>
        <template data-mlm-template="field-item-table-row">
            <tr data-mlm-choice>
                <td class="mlm-choices-table-indicator">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="field-choice-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                </td>
                <td class="mlm-choices-table-code">
                    <label data-mlm-display="code" for></label>
                </td>
                <td class="mlm-choices-table-translation">
                    <input type="text" data-mlm-translation data-mlm-name data-mlm-type data-mlm-index data-mlm-refhash class="form-control form-control-sm mlm-textarea" id="" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                </td>
            </tr>
        </template>
        <!--#endregion-->
        <!--#region Survey Settings Templates -->
        <template data-mlm-template="survey-setting-exclusion-table">
            <table class="table table-responsive table-md">
                <thead>
                    <tr>
                        <th scope="col"><?=RCView::tt("multilang_92") // Setting ?></th>
                        <th scope="col"><?=RCView::tt("multilang_84") // Excluded ?></th>
                    </tr>
                </thead>
                <tbody data-mlm-form></tbody>
            </table>
        </template>
        <template data-mlm-template="survey-setting-exclusion-row">
            <tr data-mlm-setting data-mlm-language>
                <td>
                    <div class="mlm-text-cell">
                        <span data-mlm-display="settingName"></span>
                    </div>
                </td>
                <td class="text-center">
                    <div class="mlm-radio-cell">
                        <input data-mlm-type="setting-excluded" type="checkbox" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_93") // Check to exclude this setting from being translated ?>">
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="survey-setting-title">
            <div class="mlm-survey-setting-title">
                <h5 class="mlm-sub-category-subheading"><span data-mlm-display="title"></span></h5>
            </div>
        </template>
        <template data-mlm-template="survey-setting-text">
            <div data-mlm-survey-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <input type="text" data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea" id="" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                </div>
            </div>
        </template>
        <template data-mlm-template="survey-setting-textarea">
            <div data-mlm-survey-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <div class="mlm-with-rte">
                        <textarea data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea textarea-autosize" id="" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                        <button data-mlm-action="rich-text-editor" data-mlm-rtemode="normal" data-mlm-name data-mlm-type data-mlm-index="" class="btn btn-xs btn-outline-secondary mlm-rte-button">
                            <?=RCView::tt("multilang_90") // Rich Text Editor ?>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        <template data-mlm-template="survey-setting-select">
            <div data-mlm-survey-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <select data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" class="form-control form-control-sm mlm-textarea" id="" placeholder="">
                        <option value=""><?=RCView::tt_attr("multilang_105") // -- Select an option -- ?></option>
                    </select>
                </div>
            </div>
        </template>
        <!--#endregion-->
        <!--#region ASI Templates -->
        <template data-mlm-template="asi-ref-lang-notice">
            <p><i><?=RCView::tt("multilang_800")?></i></p>
        </template>
        <template data-mlm-template="asi-settings">
            <div data-mlm-asi class="mlm-field-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="asi-translated" class="badge badge-light">&nbsp;</span>
                    <label class="mlm-field-name" data-mlm-display="event-name" for=""></label> (<span data-mlm-display="unique-event-name"></span>)
                </div>
                <div class="mlm-asi-items">
                    <!-- Items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="asi-setting-text">
            <div data-mlm-asi-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="asi-setting-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <input type="text" id data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm  mlm-textarea" id="" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                </div>
            </div>
        </template>
        <template data-mlm-template="asi-setting-textarea">
            <div data-mlm-asi-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="asi-setting-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <div class="mlm-with-rte">
                        <textarea id data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea textarea-autosize" id="" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                        <button data-mlm-action="rich-text-editor" data-mlm-rtemode="normal" data-mlm-name data-mlm-type data-mlm-index="" class="btn btn-xs btn-outline-secondary mlm-rte-button">
                            <?=RCView::tt("multilang_90") // Rich Text Editor ?>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        <!--#endregion-->
        <!--#region Reference Value Template -->
        <template data-mlm-template="reference-value">
            <div class="mlm-reference form-inline">
                <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Default text: ?></span>
                <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                    <i class="far fa-check-circle"></i>
                </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                    <i class="far fa-copy"></i>
                </button> 
                <span class="mlm-reference-value" data-mlm-display="reference"  data-mlm-searchable></span>
            </div>
            <div class="mlm-ref-has-embeddings"><?=\RCIcon::ErrorNotificationTriangle("text-danger me-1")?><?=RCView::tt("multilang_792")?></div>
        </template>
        <!--#endregion-->
    </div>
</div>
<?php
#endregion

#region MyCap
?>
<div data-mlm-tab="mycap" class="d-none">
    <!-- Language Switcher -->
    <div class="mlm-language-switcher">
        <div class="mlm-switcher-buttons"></div>
    </div>
    <!-- MyCap Category Nav -->
    <div class="mlm-sub-category-nav nav d-block">
        <ul class="nav nav-tabs">
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="mycap-app_title" class="nav-link mlm-sub-category-link"><?=RCView::tt("multilang_752") // App Title & Baseline Date Task ?></a>
            </li>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="mycap-about" class="nav-link mlm-sub-category-link"><?=RCView::tt("mycap_mobile_app_02") // About ?></a>
            </li>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="mycap-contacts" class="nav-link mlm-sub-category-link"><?=RCView::tt("mycap_mobile_app_03") // Contacts ?></a>
            </li>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="mycap-links" class="nav-link mlm-sub-category-link"><?=RCView::tt("mycap_mobile_app_04") // Links ?></a>
            </li>
        </ul>
    </div>
    <!-- Content -->
    <p class="mlm-reflang-notice yellow mt-3"><?=RCView::tt("multilang_803")?></p>
    <div class="mlm-mycap-category-tabs hide-when-ref-lang">
        <!-- App Title -->
        <div class="mlm-mycap-category-tab" data-mlm-sub-category="mycap-app_title">
            <!-- AI Translation Tool -->
            <?php
            if ($ai_mlmtranslator_service_enabled) {
                print MultiLanguage::getAITranslatorActionHTML();
            }
            ?>
            <div data-mlm-mycap-app_title-items class="">
                <h5 class="mlm-sub-category-subheading mt-1"><?=RCView::tt("home_30") // Project Title ?></h5>
                <div data-mlm-mycap-setting="app_title" class="mlm-field-block">
                    <div class="form-group">
                        <div class="mlm-item-translated-indicator">
                            <span data-mlm-indicator="mycap-setting-translated" class="badge badge-light" >&nbsp;</span>
                        </div>
                        <label class="mlm-translation-prompt" for="mycap-app_title"><?=$proj_meta["myCap"]["mycap-app_title"]["prompt"]?></label>
                        <div class="mlm-reference">
                            <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                            <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Default text: ?></span>
                            <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                                <i class="far fa-check-circle"></i>
                            </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                                <i class="far fa-copy"></i>
                            </button> 
                            <span class="mlm-reference-value"><?=$proj_meta["myCap"]["mycap-app_title"]["reference"]?></span>
                        </div>
                        <input type="text" data-mlm-translation data-mlm-type="mycap-app_title" data-mlm-name="" data-mlm-index="" data-mlm-refhash="<?=$proj_meta["myCap"]["mycap-app_title"]["refHash"]?>" class="form-control form-control-sm mlm-textarea" id="mycap-app_title" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                    </div>
                </div>
                <?php if ($proj_meta["myCap"]["mycap-baseline_task"]): ?>
                <h5 class="mlm-sub-category-subheading"><?=RCView::tt("multilang_743") // Baseline Date Task ?></h5>
                <?php 
                foreach ($proj_meta["myCap"]["mycap-baseline_task"] as $this_key => $this_item): 
                    if (empty($this_item["reference"])) continue;
                ?>
                <div data-mlm-mycap-setting="mycap-baseline_task" class="mlm-field-block">
                    <div class="form-group">
                        <div class="mlm-item-translated-indicator">
                            <span data-mlm-indicator="mycap-setting-translated" class="badge badge-light" >&nbsp;</span>
                        </div>
                        <label class="mlm-translation-prompt" for="<?=$this_key?>"><?=$this_item["prompt"]?></label>
                        <div class="mlm-reference">
                            <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                            <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Default text: ?></span>
                            <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                                <i class="far fa-check-circle"></i>
                            </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                                <i class="far fa-copy"></i>
                            </button> 
                            <span class="mlm-reference-value"><?=$this_item["reference"]?></span>
                        </div>
                        <textarea data-mlm-translation data-mlm-type="mycap-baseline_task" data-mlm-name="<?=$this_key?>" data-mlm-index="" data-mlm-refhash="<?=$this_item["refHash"]?>" class="form-control form-control-sm mlm-textarea textarea-autosize" id="<?=$this_key?>" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                    </div>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- About -->
        <div class="mlm-mycap-category-tab hide" data-mlm-sub-category="mycap-about">
            <!-- AI Translation Tool -->
            <?php
            if ($ai_mlmtranslator_service_enabled) {
                print MultiLanguage::getAITranslatorActionHTML();
            }
            ?>
            <div data-mlm-render="mycap-settings"></div>
            <p class="show-when-no-mycap-settings mt-5">
                <i><?=RCView::tt("multilang_733") // There are no MyCap settings in this category that can be translated. ?></i>
            </p>
            <p class="mlm-mycap-setting-no-items mt-5 hide"><i><?=RCView::tt("multilang_103") // There are no items matching the current filter criteria. ?></i></p>
        </div>
        <!-- Contacts -->
        <div class="mlm-mycap-category-tab hide" data-mlm-sub-category="mycap-contacts">
        <!-- AI Translation Tool -->
        <?php
        if ($ai_mlmtranslator_service_enabled) {
            print MultiLanguage::getAITranslatorActionHTML();
        }
        ?>
        <div data-mlm-render="mycap-settings"></div>
            <p class="show-when-no-mycap-settings mt-5">
                <i><?=RCView::tt("multilang_733") // There are no MyCap settings in this category that can be translated. ?></i>
            </p>
            <p class="mlm-mycap-setting-no-items mt-5 hide"><i><?=RCView::tt("multilang_103") // There are no items matching the current filter criteria. ?></i></p>
        </div>
        <!-- Links -->
        <div class="mlm-mycap-category-tab hide" data-mlm-sub-category="mycap-links">
        <!-- AI Translation Tool -->
        <?php
        if ($ai_mlmtranslator_service_enabled) {
            print MultiLanguage::getAITranslatorActionHTML();
        }
        ?>
        <div data-mlm-render="mycap-settings"></div>
            <p class="show-when-no-mycap-settings mt-5">
                <i><?=RCView::tt("multilang_733") // There are no MyCap settings in this category that can be translated. ?></i>
            </p>
            <p class="mlm-mycap-setting-no-items mt-5 hide"><i><?=RCView::tt("multilang_103") // There are no items matching the current filter criteria. ?></i></p>
        </div>
        <!--#region Search Tool -->
        <div id="mycap-search-tool" class="mlm-search-tool hide-when-no-mycap-settings hide">
            <label id="mlm-mycap-search-box-label" for="mlm-mycap-search-box"><?=RCView::tt("multilang_49") // Filter items on this page: ?></label>
            <input data-mlm-config="mycap-search" data-mlm-type="search-tool" type="search" class="form-control form-control-sm" id="mlm-mycap-search-box" aria-describedby="mlm-mycap-search-box-label" placeholder="<?=RCView::tt_attr("multilang_50") // Search for anything... ?>">
            <a href="javascript:;" data-mlm-action="mycap-collapse-all" class="ms-2">
                <?=RCView::tt("multilang_157") // Collapse all ?>
            </a> &nbsp;|&nbsp;
            <a href="javascript:;" data-mlm-action="mycap-expand-all">
                <?=RCView::tt("multilang_158") // Expand all ?>
            </a>
        </div>
        <!--#endregion-->
        <!--#region MyCap Templates-->
        <template data-mlm-template="mycap-setting">
            <div data-mlm-mycap-setting class="mlm-field-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="mycap-translated" class="badge badge-light">&nbsp;</span>
                    <a href="javascript:;" data-mlm-mycap-id data-mlm-mycap-item-type data-mlm-action="mycap-toggle-collapse" class="mlm-mycap-toggle">
                        <b><span data-mlm-display="mycap-item-kind"></span> #<span data-mlm-display="mycap-number"></span></b> &ndash; <span data-mlm-display="mycap-title" data-mlm-searchable></span>
                        <i class="fa-solid fa-angle-up hide-when-collapsed ms-2"></i>
                        <i class="fa-solid fa-angle-down hide show-when-collapsed ms-2"></i>
                    </a>
                </div>
                <div class="mlm-mycap-items hide-when-collapsed">
                    <!-- Items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="mycap-setting-text">
            <div data-mlm-mycap-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="mycap-setting-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <input type="text" data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea" id="" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                </div>
            </div>
        </template>
        <template data-mlm-template="mycap-setting-textarea">
            <div data-mlm-mycap-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="mycap-setting-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <div class="mlm-with-rte">
                        <textarea data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea textarea-autosize" id="" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                        <button data-mlm-action="rich-text-editor" data-mlm-rtemode="normal" data-mlm-name data-mlm-type data-mlm-index="" class="btn btn-xs btn-outline-secondary mlm-rte-button">
                            <?=RCView::tt("multilang_90") // Rich Text Editor ?>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        <!--#endregion-->
    </div>
</div>
<?php 

#endregion

#region Alerts
//
?>
<div data-mlm-tab="alerts" class="d-none">
    <!-- Language Switcher -->
    <div class="mlm-language-switcher">
        <div class="mlm-switcher-buttons"></div>
    </div>
    <!-- Content -->
    <p class="mlm-reflang-notice yellow"><?=RCView::tt("multilang_796")?></p>
    <!-- Alerts Exclusion Table -->
    <div data-mlm-mode="alerts-exclusion">
        <p><?=RCView::tt("multilang_153") // Use this page to manage which alerts should be translated. Alerts that are turned off will not show up for (nor be translated into) other languages. Note that since alerts can be set up for various purposes and triggered in different contexts, it is not possible to automatically determine the language that should be used to render an alert. Therefore, for each alert, the source for the language information needs to be set. This can either be the <b>'Language preference field'</b> (as set on the 'Languages' tab; this is the default), or the <b>'User's or survey respondent's active language'</b> (as set in their profile/browser cookie). ?></p>
        <table class="table table-responsive table-md">
            <thead>
                <tr>
                    <th scope="col"><?=RCView::tt("multilang_73") // ID ?></th>
                    <th scope="col"><?=RCView::tt("alerts_24") // Alert ?></th>
                    <th scope="col"><?=RCView::tt("multilang_84") // Excluded ?></th>
                    <th scope="col"><?=RCView::tt("multilang_208") // Language Source ?></th>
                </tr>
            </thead>
            <tbody id="mlm-alets-exclusion-rows">
            </tbody>
        </table>
    </div>
    <!-- Alerts Translation -->
    <div data-mlm-mode="alerts-translation">
        <!-- Search Tool -->
        <div class="mlm-search-tool hide-when-no-alerts">
            <label id="mlm-alerts-search-box-label" for="mlm-alerts-search-box"><?=RCView::tt("multilang_49") // Filter items on this page: ?></label>
            <input data-mlm-config="alerts-search" data-mlm-type="search-tool" type="search" class="form-control form-control-sm" id="mlm-alerts-search-box" aria-describedby="mlm-alerts-search-box-label" placeholder="<?=RCView::tt_attr("multilang_50") // Search for anything... ?>">
            <a href="javascript:;" data-mlm-action="alerts-collapse-all" class="ms-2">
                <?=RCView::tt("multilang_157") // Collapse all ?>
            </a> &nbsp;|&nbsp;
            <a href="javascript:;" data-mlm-action="alerts-expand-all">
                <?=RCView::tt("multilang_158") // Expand all ?>
            </a>
        </div>
        <!-- AI Translation Tool -->
        <?php
        if ($ai_mlmtranslator_service_enabled) {
            print MultiLanguage::getAITranslatorActionHTML();
        }
        ?>
        <!-- Alerts Settings -->
        <div data-mlm-render="alerts"></div>
        <p class="show-when-no-alerts mt-5">
            <i><?=RCView::tt("multilang_156") // There are no alerts that can be translated. ?></i>
        </p>
        <p class="mlm-alerts-no-items mt-5 hide"><i><?=RCView::tt("multilang_103") // There are no items matching the current filter criteria. ?></i></p>
    </div>
    <!-- Templates -->
    <div class="d-none">
        <!--#region Alert Templates -->
        <template data-mlm-template="alert-exclusion-row">
            <tr data-mlm-alert>
                <th scope="row">
                    <div class="mlm-text-cell">
                        <span data-mlm-display="alert-id"></span>
                    </div>
                </th>
                <td>
                    <div class="mlm-text-cell">
                        <span data-mlm-display="alert-number"></span>
                        <span data-mlm-display="alert-name"></span>
                    </div>
                </td>
                <td>
                    <div class="mlm-radio-cell">
                        <input data-mlm-type="alert-excluded" data-mlm-name type="checkbox" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_155") // Check to exclude this alert from being translated ?>">
                    </div>
                </td>
                <td>
                    <div class="mlm-select-cell">
                        <select data-mlm-type="alert-source" data-mlm-name>
                            <option value="field" selected><?=RCView::tt_attr("multilang_209") // Language preference field ?></option>
                            <option value="user"><?=RCView::tt_attr("multilang_210") // User's or survey respondent's active language ?></option>
                        </select>
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="no-alerts-exclusion-row">
            <tr>
                <td colspan="3">
                    <div class="mlm-text-cell">
                        <i><?=RCView::tt("multilang_174") // There are no alerts in this project. ?></i>
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="alert-settings">
            <div data-mlm-alert class="mlm-field-block">
                <hr>
                <div class="mlm-item-translated-indicator">
                    <span data-mlm-indicator="alert-translated" class="badge badge-light">&nbsp;</span>
                    <a href="javascript:;" data-mlm-alert-id data-mlm-action="alert-toggle-collapse" class="mlm-alert-toggle">
                        <b><?=RCView::tt("alerts_24") // Alert ?> <span data-mlm-display="alert-number"></span> <span data-mlm-display="alert-name"></span></b>
                        [ <span data-mlm-display="alert-id"></span> ]
                        <i class="fa-solid fa-angle-up hide-when-collapsed ms-2"></i>
                        <i class="fa-solid fa-angle-down hide show-when-collapsed ms-2"></i>
                    </a>
                </div>
                <div class="mlm-alert-items hide-when-collapsed">
                    <!-- Items -->
                </div>
            </div>
        </template>
        <template data-mlm-template="alert-setting-text">
            <div data-mlm-alert-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="alert-setting-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <input type="text" id data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea" id="" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                </div>
            </div>
        </template>
        <template data-mlm-template="alert-setting-textarea">
            <div data-mlm-alert-setting class="mlm-field-block">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="alert-setting-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt" data-mlm-display="prompt" for=""></label>
                    <div class="mlm-with-rte">
                        <textarea id data-mlm-translation data-mlm-type data-mlm-name data-mlm-index="" data-mlm-refhash class="form-control form-control-sm mlm-textarea textarea-autosize" id="" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                        <button data-mlm-action="rich-text-editor" data-mlm-rtemode="normal" data-mlm-name data-mlm-type data-mlm-index="" class="btn btn-xs btn-outline-secondary mlm-rte-button">
                            <?=RCView::tt("multilang_90") // Rich Text Editor ?>
                        </button>
                    </div>
                </div>
            </div>
        </template>
        <!--#endregion-->
    </div>
</div>
<?php
#endregion

#region Misc
$has_mdcs = count($proj_meta["mdcs"]) > 0;
$has_pdfcustomizations = count($proj_meta["pdfCustomizations"]);
$has_protmail = count($proj_meta["protectedMail"]);
$has_descriptive_popups = count($proj_meta["descriptivePopups"]);
?>
<div data-mlm-tab="misc" class="d-none">
    <!-- Language Switcher -->
    <div class="mlm-language-switcher">
        <div class="mlm-switcher-buttons"></div>
    </div>
    <!-- Content -->
    <p class="mlm-reflang-notice yellow mt-3"><?=RCView::tt("multilang_803")?></p>
    <p class="hide-when-ref-lang"><?=RCView::tt("multilang_213") // Note that some tabs on this page (such as e.g., Missing Data Codes or Protected Email) will only be shown when the corresponding features are enabled in this project. ?></p>
    <!-- AI Translation Tool -->
    <?php
    if ($ai_mlmtranslator_service_enabled) {
        print MultiLanguage::getAITranslatorActionHTML();
    }
    ?>

    <!-- Misc Category Nav -->
    <div class="mlm-sub-category-nav nav d-block">
        <ul class="nav nav-tabs hide-when-ref-lang">
            <?php if ($has_mdcs): ?>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="misc-mdc" class="nav-link mlm-sub-category-link"><?=RCView::tt("missing_data_04") // Missing Data Codes ?></a>
            </li>
            <?php endif; ?>
            <?php if ($has_pdfcustomizations): ?>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="misc-pdf" class="nav-link mlm-sub-category-link"><?=RCView::tt("global_85") // PDF ?></a>
            </li>
            <?php endif; ?>
            <?php if ($has_descriptive_popups): ?>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="misc-descriptive-popups" class="nav-link mlm-sub-category-link"><?=RCView::tt("descriptive_popups_01") // Descriptive Popups ?></a>
            </li>
            <?php endif; ?>
            <?php if ($has_protmail): ?>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="misc-protmail" class="nav-link mlm-sub-category-link"><?=RCView::tt("multilang_189") // Protected Mail ?></a>
            </li>
            <?php endif; ?>
            <!--
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="misc-soon" class="nav-link mlm-sub-category-link">Coming soon &hellip;</a>
            </li>
            -->
        </ul>
    </div>
    <div class="mlm-misc-category-tabs hide-when-ref-lang">
        <!-- Coming Soon Placeholder -->
        <!--
        <div class="mlm-misc-category-tab hide" data-mlm-sub-category="misc-soon">
            <p>
                Translation of items such as the following might be possible in the future:
                <ul>
                    <li>Event Names and Custom Event Labels</li>
                    <li>Custom Record Label</li>
                    <li>Custom label for repeating instruments</li>
                    <li>Survey Notification Emails</li>
                </ul>
            </p>
        </div>
        -->
        <?php if ($has_mdcs): ?>
        <!-- Missing Data Codes -->
        <div class="mlm-misc-category-tab hide" data-mlm-sub-category="misc-mdc">
            <div data-mlm-field-item class="mt-4">
                <div class="form-group">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="mdcs-translated" class="badge badge-light" >&nbsp;</span>
                    </div>
                    <label class="mlm-translation-prompt"><?=RCView::tt("multilang_178") // Missing Data Code Translations ?></label>
                    <table class="table table-responsive table-md mt-2 mlm-choices-table" style="width: 90%;height:auto;">
                        <thead>
                            <tr>
                                <th scope="col"></th>
                                <th scope="col"><?=RCView::tt("multilang_179") // Code ?></th>
                                <th scope="col" style="width: 100%;"><?=RCView::tt("multilang_91") // Translation ?></th>
                            </tr>
                        </thead>
                        <tbody><!-- Uses 'field-item-table-row' template --></tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($has_pdfcustomizations): ?>
        <!-- PDF Customizations -->
        <div class="mlm-misc-category-tab hide" data-mlm-sub-category="misc-pdf">
            <div data-mlm-pdf-items class="mt-4">
            <?php foreach($proj_meta["pdfCustomizations"][""] as $pdfcust_name => $pdfcust_data): ?>
                <div data-mlm-pdf-setting class="mlm-field-block">
                    <div class="form-group">
                        <div class="mlm-item-translated-indicator">
                            <span data-mlm-indicator="pdf-setting-translated" class="badge badge-light" >&nbsp;</span>
                        </div>
                        <label class="mlm-translation-prompt" for="<?=$pdfcust_name?>"><?=$pdfcust_data["prompt"]?></label>
                        <div class="mlm-reference">
                            <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                            <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Reference text: ?></span>
                            <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                                <i class="far fa-check-circle"></i>
                            </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                                <i class="far fa-copy"></i>
                            </button> 
                            <span class="mlm-reference-value"><?=$pdfcust_data["reference"]?></span>
                        </div>
                        <input type="text" data-mlm-translation data-mlm-type="<?=$pdfcust_name?>" data-mlm-name="" data-mlm-index="" data-mlm-refhash="<?=$pdfcust_data["refHash"]?>" class="form-control form-control-sm mlm-textarea" id="<?=$pdfcust_name?>" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($has_descriptive_popups): ?>
        <!-- Descriptive Popups -->
        <div class="mlm-misc-category-tab hide" data-mlm-sub-category="misc-descriptive-popups">
            <div data-mlm-descriptivepopups class="mt-3" style="margin-left:-1em;">
                <div class="mlm-search-tool">
                    <label id="mlm-descriptivepopups-search-box-label" for="mlm-descriptivepopups-search-box"><?=RCView::tt("multilang_49") // Filter items on this page: ?></label>
                    <input data-mlm-config="descriptivepopups-search" data-mlm-type="search-tool" type="search" class="form-control form-control-sm" id="mlm-descriptivepopups-search-box" aria-describedby="mlm-descriptivepopups-search-box-label" placeholder="<?=RCView::tt_attr("multilang_50") // Search for anything... ?>">
                    <a href="javascript:;" data-mlm-action="descriptivepopups-collapse-all" class="ms-2">
                        <?=RCView::tt("multilang_787") // Collapse translated ?>
                    </a> &nbsp;|&nbsp;
                    <a href="javascript:;" data-mlm-action="descriptivepopups-expand-all">
                        <?=RCView::tt("multilang_158") // Expand all ?>
                    </a>
                </div>
                <?php foreach($proj_meta["descriptivePopups"] as $popup_uid => $popup_data): ?>
                <div data-mlm-descriptivepopup="<?=$popup_uid?>" class="mlm-field-block">
                    <div class="mlm-item-translated-indicator">
                        <span data-mlm-indicator="descriptivepopup-translated" class="badge badge-light">&nbsp;</span>
                        <div style="display: inline-block;">
                            <input data-mlm-translation data-mlm-type="descriptive-popup-complete" data-mlm-index="<?=$popup_uid?>" style="position:relative;top:2.5px;margin-left:3px;" type="checkbox" id="" value="1" data-bs-toggle="tooltip" data-bs-trigger="hover" title="<?=RCView::tt_attr("multilang_86") // Check to mark this field as translated ?>">
                            <b><?=RCView::lang_i("multilang_786", [strip_tags($popup_data["inline_text"]["reference"])])?></b>
                        </div>
                        <a href="javascript:;" data-mlm-action="toggle-descriptivepopup-items" class="ms-2">
                            <i class="fa-solid fa-angle-up hidden-when-collapsed"></i>
                            <i class="fa-solid fa-angle-down shown-when-collapsed"></i>
                        </a>
                    </div>
                    <div class="mlm-field-items hide-when-collapsed">
                        <!-- Items -->
                        <div data-mlm-descriptivepopup-item class="mlm-field-block">
                            <div class="form-group">
                                <div class="mlm-item-translated-indicator">
                                    <span data-mlm-indicator="descriptivepopup-item-translated" class="badge badge-light" >&nbsp;</span>
                                </div>
                                <label class="mlm-translation-prompt" for="inline_text-<?=$popup_uid?>"><?=$popup_data["inline_text"]["prompt"]?></label>
                                <div class="mlm-reference">
                                    <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                                    <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Reference text: ?></span>
                                    <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                                        <i class="fa-regular fa-check-circle"></i>
                                    </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                                        <i class="fa-regular fa-copy"></i>
                                    </button> 
                                    <span class="mlm-reference-value"><?=htmlentities($popup_data["inline_text"]["reference"])?></span>
                                </div>
                                <input type="text" data-mlm-translation data-mlm-type="descriptive-popup" data-mlm-name="inline_text" data-mlm-index="<?=$popup_uid?>" data-mlm-refhash="<?=$popup_data["inline_text"]["refHash"]?>" class="form-control form-control-sm mlm-textarea" id="inline_text-<?=$popup_uid?>" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>">
                            </div>
                        </div>
                        <div data-mlm-descriptivepopup-item class="mlm-field-block">
                            <div class="form-group">
                                <div class="mlm-item-translated-indicator">
                                    <span data-mlm-indicator="descriptivepopup-item-translated" class="badge badge-light" >&nbsp;</span>
                                </div>
                                <label class="mlm-translation-prompt" for="inline_text_popup_description-<?=$popup_uid?>"><?=$popup_data["inline_text_popup_description"]["prompt"]?></label>
                                <div class="mlm-reference">
                                    <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                                    <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Reference text: ?></span>
                                    <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                                        <i class="fa-regular fa-check-circle"></i>
                                    </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                                        <i class="fa-regular fa-copy"></i>
                                    </button> 
                                    <span class="mlm-reference-value"><?=htmlentities($popup_data["inline_text_popup_description"]["reference"])?></span>
                                </div>
                                <div class="mlm-with-rte">
                                    <textarea data-mlm-translation data-mlm-type="descriptive-popup" data-mlm-name="inline_text_popup_description" data-mlm-index="<?=$popup_uid?>" data-mlm-refhash="<?=$popup_data["inline_text_popup_description"]["refHash"]?>" class="form-control form-control-sm mlm-textarea textarea-autosize" id="inline_text_popup_description-<?=$popup_uid?>" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                                    <button data-mlm-action="rich-text-editor" data-mlm-rtemode="normal" data-mlm-type="descriptive-popup" data-mlm-name="inline_text_popup_description" data-mlm-index="<?=$popup_uid?>" class="btn btn-xs btn-outline-secondary mlm-rte-button">
                                        <?=RCView::tt("multilang_90") // Rich Text Editor ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
                <p class="mlm-descriptivepopups-no-items mt-5 hide"><i><?=RCView::tt("multilang_103") // There are no items matching the current filter criteria. ?></i></p>
            </div>
        </div>
        <?php endif; ?>
        <?php if ($has_protmail): ?>
        <!-- Protected Email -->
        <div class="mlm-misc-category-tab hide" data-mlm-sub-category="misc-protmail">
            <div data-mlm-pdf-items class="mt-4">
            <?php foreach($proj_meta["protectedMail"][""] as $pe_name => $pe_data): ?>
                <div data-mlm-protmail-setting class="mlm-field-block">
                    <div class="form-group">
                        <div class="mlm-item-translated-indicator">
                            <span data-mlm-indicator="protmail-setting-translated" class="badge badge-light" >&nbsp;</span>
                        </div>
                        <label class="mlm-translation-prompt" for="<?=$pe_name?>"><?=$pe_data["prompt"]?></label>
                        <div class="mlm-reference">
                            <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                            <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Reference text: ?></span>
                            <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                                <i class="far fa-check-circle"></i>
                            </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard)?>">
                                <i class="far fa-copy"></i>
                            </button> 
                            <span class="mlm-reference-value"><?=$pe_data["reference"]?></span>
                        </div>
                        <div class="mlm-with-rte">
                            <textarea data-mlm-translation data-mlm-type="<?=$pe_name?>" data-mlm-name="" data-mlm-index="" data-mlm-refhash="<?=$pe_data["refHash"]?>" class="form-control form-control-sm mlm-textarea textarea-autosize" id="<?=$pe_name?>" rows="1" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
                            <button data-mlm-action="rich-text-editor" data-mlm-rtemode="normal" data-mlm-name="" data-mlm-type="<?=$pe_name?>" data-mlm-index="" class="btn btn-xs btn-outline-secondary mlm-rte-button">
                                <?=RCView::tt("multilang_90") // Rich Text Editor ?>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>
<?php 

#endregion

;elseif(false):

#region Defaults
?>
<div data-mlm-tab="defaults" class="d-none">
    <!-- Language Switcher -->
    <div class="mlm-language-switcher">
        <div class="mlm-switcher-buttons"></div>
    </div>
    <!-- Content -->
    <p>
        Not implemented yet.
    </p>
    <p>
        This will (eventually) allow translation of miscellaneous defaults such as:
        <ul>
            <li>Preset survey setting texts</li>
            <li>Default ASI email content</li>
            <li>Default Missing Data Code labels</li>
        </ul>
    </p>
</div>
<?php
#endregion

;endif;

#region User Interface
?>
<div data-mlm-tab="ui" class="d-none">
    <!-- Language Switcher -->
    <div class="mlm-language-switcher">
        <div class="mlm-switcher-buttons"></div>
    </div>
    <p>
		<?=RCView::tt("multilang_220") // You may translate any of the REDCap's stock user interface elements... ?>
    </p>
    <p class="show-when-subscribed yellow">
        <?=RCView::tt("multilang_666") // This is a subscribed system language. Therefore, user interface items cannot be edited. ?>
    </p>
    <p class="show-when-can-override">
        <i class="fa-solid fa-circle-exclamation text-danger"></i> <?=RCView::tt("multilang_667") // Note, this is a subscribed system language, but you may provide select overrides of individual items. ?>
    </p>

    <!-- Search Tool -->
    <div class="mlm-search-tool hide-when-subscribed">
        <label id="mlm-ui-search-box-label" for="mlm-ui-search-box"><?=RCView::tt("multilang_49") // Filter items on this page: ?></label>
        <input data-mlm-config="ui-search" data-mlm-type="search-tool" type="search" class="form-control form-control-sm" id="mlm-ui-search-box" aria-describedby="mlm-ui-search-box-label" placeholder="<?=RCView::tt_attr("multilang_50") // Search for anything... ?>">
        <div>
            <input class="form-check-input" type="checkbox" data-mlm-action="toggle-hide-ui-translated" name="ui-hide-translated-ui" id="ui-hide-translated-ui">
            <label for="ui-hide-translated-ui" class="form-check-label">
                <?=RCView::tt("multilang_51") // Hide translated items ?> 
            </label>
        </div>
    </div>
    <!-- AI Translation Tool -->
    <?php
    if ($ai_mlmtranslator_service_enabled) {
        print MultiLanguage::getAITranslatorActionHTML();
    }
    ?>
    <!-- UI Category Nav -->
    <div class="mlm-sub-category-nav nav hide-when-subscribed">
        <ul class="nav nav-tabs">
        <?php 
        foreach ($ui_categories as $cat => $display) {
            if (strpos($cat, "_") !== false) continue;
        ?>
            <li class="nav-item">
                <a href="javascript:;" data-mlm-action="cat-nav" data-mlm-sub-category="<?=$cat?>" class="nav-link mlm-sub-category-link"><?=$display?></a>
            </li>
        <?php } ?>
        </ul>
    </div>
    <div class="mlm-ui-translations hide-when-subscribed">
        <?php $item = 0; foreach ($ui_meta as $id => $meta) { 
            $item++;
            $badge = $meta["type"] == "bool" ? "badge badge-secondary" : "badge"; // Default badge class for switches
        ?>
        <div data-mlm-ui-translation data-mlm-group="<?=$meta["group"]?>" class="mlm-translation-item form-group <?=$meta["category"]?>">
            <div class="mlm-item-translated-indicator">
                <span data-mlm-indicator="translated" class="<?=$badge?>" >&nbsp;</span>
            </div>
            <label class="mlm-translation-prompt" for="mlm-item-<?=$item?>"><?=$meta["prompt"]?></label>
            <?php if ($meta["type"] == "string") { ?>
                <div class="mlm-reference">
                    <span data-mlm-ref-changed class="badge badge-warning" style="min-width:3em;">&nbsp;</span>
                    <span class="mlm-reference-title"><?=RCView::tt("multilang_94") // Reference text: ?></span>
                    <button data-mlm-ref-changed data-mlm-action="accept-ref-change" class="btn btn-xs text-danger btn-link" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_95") // Mark this translation as adequate for the changed reference ?>" style="display:none;">
                        <i class="far fa-check-circle"></i>
                    </button><button data-mlm-action="copy-reference" class="btn btn-xs btn-link copy-reference" data-bs-toggle="tooltip" title="<?=RCView::tt_attr("multilang_96") // Copy reference value to the clipboard ?>"><i class="far fa-copy"></i></button>
                    <span class="mlm-reference-value"><?=htmlentities($meta["default"])?></span>
                </div>
                <textarea rows="1" class="form-control mlm-textarea textarea-autosize" id="mlm-item-<?=$item?>" data-mlm-translation="<?=$id?>" data-mlm-type="ui" data-mlm-refhash="<?=$meta["refHash"]?>" placeholder="<?=RCView::tt_attr("multilang_89") // Enter translation ?>"></textarea>
            <?php } else if ($meta["type"] == "bool") { ?>
                <div class="mlm-translation-toggle">
                    <span class="switch switch-xs">
                        <input type="checkbox" class="switch" data-mlm-translation="<?=$id?>" id="mlm-item-<?=$item?>">
                        <label for="mlm-item-<?=$item?>"></label>
                    </span>
                </div>
            <?php } ?>
        </div>
        <?php } // foreach ?>
        <p class="mlm-ui-no-items hide"><i><?=RCView::tt("multilang_103") // There are no items matching the current filter criteria. ?></i></p>
    </div>
</div>
<?php
#endregion

if ($is_project) {

#region Project Settings Tab 
?>
<div data-mlm-tab="settings" class="d-none pt-2">
    <div class="mlm-option">
        <label for="switch-highlightMissingDataentry" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="highlightMissingDataentry" id="switch-highlightMissingDataentry">
                <label for="switch-highlightMissingDataentry"></label>
            </span>
            <?=RCView::tt("multilang_15") // Highlight untranslated text on Data Entry pages that should be translated ?>
        </label>
    </div>
    <div class="mlm-option">
        <label for="switch-highlightMissingSurvey" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="highlightMissingSurvey" id="switch-highlightMissingSurvey">
                <label for="switch-highlightMissingSurvey"></label>
            </span>
            <?=RCView::tt("multilang_16") // Highlight untranslated text on Survey pages that should be translated ?>
        </label>
    </div>
    <div class="mlm-setting-option-text">
        <?= RCView::tt("multilang_677") // The options above facilitate translation during project development. For <small><b>PRODUCTION</b></small> projects, it is recommended to turn off the highlight options. ?>
    </div>
    <div class="mlm-option">
        <label for="switch-autoDetectBrowserLang" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="autoDetectBrowserLang" id="switch-autoDetectBrowserLang">
                <label for="switch-autoDetectBrowserLang"></label>
            </span>
            <?=RCView::tt("multilang_623") // Attempt to match initially displayed language with preferred languages set in web browsers ?>
        </label>
    </div>
    <div class="mlm-setting-option-text">
        <?= RCView::tt("multilang_678") // When this option is enabled, REDCap will to try to match a user's/survey respondent's preferred language, as set in their web browser, to the languages available in this project. Note that for this to work, the languages' IDs <b>must</b> match a valid ISO code. Autodetection will only take effect in case the user has not actively chosen a language yet, i.e. on their first visit. ?>
        <span class="mlm-input-description">
            <a href="<?=RCView::tt_attr("multilang_624")?>" target="_blank"><u><?=RCView::tt("multilang_205")?></u></a>
            <a href="<?=RCView::tt_attr("multilang_626")?>" target="_blank"><u><?=RCView::tt("multilang_627")?></u></a>
        </span>
    </div>
    <hr>
    <div class="mlm-option">
        <label for="switch-disabled" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="disabled" id="switch-disabled">
                <label for="switch-disabled"></label>
            </span>
            <?=RCView::tt("multilang_11") // Disable (i.e. turn off) multi-language support for this project ?>
        </label>
    </div>
    <?php if (UserRights::isSuperUserNotImpersonator()): ?>
    <hr>
    <p><?=RCView::tt("multilang_18", "b", [ "class" => "text-danger" ]) // Admin-only settings ?></p>
    <?php if($require_admin_activation): ?>
    <div class="mlm-option">
        <label for="switch-admin-enabled" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="admin-enabled" id="switch-admin-enabled">
                <label for="switch-admin-enabled"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_631") // <b>Enable</b> multi-language support for this project ?>
            </span>
        </label>
        <?php if ($langs_defined): ?>
        <div class="mlm-settings-option-text">
            <?=RCView::tt("multilang_632", "span", ["class" => "small text-danger"]) // Note, since there already is at least one language defined, multi-language support will remain available irrespective of the state of this switch. However, unless switched on, once all languages are deleted, the Multi-Language Management menu link will be hidden and users will be locked out of the Multi-Language Management page. ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <div class="mlm-option mb-0">
        <label for="switch-admin-disabled" class="mlm-setting-option mb-0">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="admin-disabled" id="switch-admin-disabled">
                <label for="switch-admin-disabled"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_633") // <b>Disable</b> multi-language support for this project ?>
            </span>
        </label>
    </div>
    <div class="mlm-setting-option-text">
        <?=RCView::tt("multilang_634") // Turning on this option will hide the Multi-Language Management menu link and prevent access to Multi-Language Management for users even when there are languages defined. ?>
    </div>
    <div class="mlm-setting-option-text">
        <?=RCView::tt("multilang_663") // Override language creation, forced subscription, and UI override system settings. To allow these options, check the following: ?>
    </div>
    <div class="mlm-setting-option-text fst-normal">
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="allow-from-file" data-mlm-config="allow-from-file">
            <label class="form-check-label" for="allow-from-file">
                Initialize from file
            </label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="allow-from-scratch" data-mlm-config="allow-from-scratch">
            <label class="form-check-label" for="allow-from-scratch">
                Initialize from scratch
            </label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="optional-subscription" data-mlm-config="optional-subscription">
            <label class="form-check-label" for="optional-subscription">
                Optional subscription
            </label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="checkbox" id="allow-ui-overrides" data-mlm-config="allow-ui-overrides">
            <label class="form-check-label" for="allow-ui-overrides">
                UI overrides
            </label>
        </div>
    </div>
    <div class="mlm-option">
        <label for="switch-debug" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="debug" id="switch-debug">
                <label for="switch-debug"></label>
            </span>
            <?=RCView::tt("multilang_17") // Debug mode (status messages will be output to the browser console) ?>
        </label>
    </div>
    <?php endif; // Super User ?>
    <!-- Templates -->
    <div class="d-none">
        <template data-mlm-template="snapshot-row">
            <tr data-mlm-snapshot>
                <td class="mlm-vertical-baseline">
                    <div class="mlm-text-cell">
                        <span data-mlm-display="timestamp"></span>
                    </div>
                </td>
                <td class="mlm-vertical-baseline">
                    <div class="mlm-text-cell">
                        <span data-mlm-display="user"></span>
                    </div>
                </td>
                <td>
                    <div class="mlm-radio-cell remove-when-deleted">
                        <button data-mlm-action="download-snapshot" data-mlm-snapshot class="btn btn-sm text-primary"><i class="fas fa-file-download"></i></button>
                        |
                        <button data-mlm-action="delete-snapshot" data-mlm-snapshot class="btn btn-light btn-sm text-danger"><i class="far fa-trash-alt"></i></button>
                    </div>
                    <div class="mlm-radio-cell remove-when-not-deleted" data-bs-toggle="popover" style="cursor: pointer;">
                        <span class="badge badge-danger"><?=RCView::tt("global_106") // Deleted ?></span>
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="snapshots-loading">
            <tr>
                <td class="mlm-vertical-baseline" colspan="3">
                    <div class="mlm-text-cell">
                        <i class="fas fa-spinner fa-spin"></i> <?=RCView::tt("data_entry_64") // Loading ... ?>
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="snapshots-loading-failed">
            <tr>
                <td class="mlm-vertical-baseline" colspan="3">
                    <div class="mlm-text-cell">
                        <i class="fas fa-exclamation-circle text-danger"></i> <?=RCView::tt("multilang_163") // Failed to load the snapshots table. Please try again after reloading this page. ?>
                    </div>
                </td>
            </tr>
        </template>
        <template data-mlm-template="no-snapshots-row">
            <tr>
                <td class="mlm-vertical-baseline" colspan="3">
                    <div class="mlm-text-cell">
                        <i data-mlm-display="message"></i> 
                    </div>
                </td>
            </tr>
        </template>
    </div>
</div>
<?php 
#endregion

} else {

#region Usage Tab
?>
<div data-mlm-tab="usage" class="d-none">
    <p><?=RCView::tt("multilang_687"); //= This page ... ?></p>
    <p data-mlm-visibility="hide-when-usage-loaded"><i class="fas fa-spinner fa-spin"></i> <?=RCView::tt("data_entry_64"); //= Loading ... ?></p>
    <div data-mlm-visibility="show-when-usage-loaded">
        <div class="mlm-usage-controls">
            <div class="mlm-option">
                <p class="mlm-description mlm-setting-option">
                    <label for="switch-show-all-projects">
                        <span class="switch switch-xs switch-inline">
                            <input type="checkbox" class="switch" data-mlm-switch="show-all-projects" id="switch-show-all-projects">
                            <label for="switch-show-all-projects"></label>
                        </span>
                        <span>
                            <?=RCView::tt("multilang_638") //= Show all projects ?>
                        </span>
                    </label>
                </p>
            </div>
            <div>
                <button data-mlm-action="refresh-usage" class="btn btn-success btn-xs"><i class="fas fa-redo-alt"></i> <?=RCView::tt("control_center_4471") //= Refresh ?></button>
                <button data-mlm-action="export-usage" class="btn btn-defaultrc btn-xs"><i class="fas fa-file-excel text-success"></i> <?=RCView::tt("global_71") //= Export ?></button>
            </div>
        </div>
        <div class="mlm-usage-stats">
            <table style="width:100%;" class="hover row-border"></table>
        </div>
    </div>
    <p data-mlm-visibility="show-when-usage-loaded">
        <b><?=RCView::tt("multilang_645")?></b>
        <br><i class="ms-2 fas fa-toggle-off"></i> <?=RCView::tt("multilang_642")?>
        <br><i class="ms-2 fas fa-user-check text-success"></i> <?=RCView::tt("multilang_644")?>
        <br><i class="ms-2 fas fa-user-lock text-danger"></i> <?=RCView::tt("multilang_643")?>
        <br><i class="ms-2 fas fa-bug"></i> <?=RCView::tt("multilang_641")?>
    </p>
</div>
<?php 
#endregion

#region System Settings Tab
?>
<div data-mlm-tab="settings" class="d-none pt-2">
    <div class="mlm-option">
        <label for="switch-highlightMissing" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="highlightMissing" id="switch-highlightMissing">
                <label for="switch-highlightMissing"></label>
            </span>
            <?=RCView::tt("multilang_145") // Highlight translation fallbacks on non-project pages ?>
        </label>
    </div>
    <hr>
    <div class="mlm-option">
        <label for="switch-disabled" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="disabled" id="switch-disabled">
                <label for="switch-disabled"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_12") // Disable (i.e. turn off) multi-language support for all projects ?>
            </span>
        </label>
    </div>
    <div class="mlm-setting-option-text">
        <?=RCView::tt("multilang_13", "b", array("class" => "text-danger")) // WARNING: THIS WILL AFFECT ALL PROJECTS! ?>
    </div>
    <hr>
    <div class="mlm-option">
        <label for="switch-require-admin-activation" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="require-admin-activation" id="switch-require-admin-activation">
                <label for="switch-require-admin-activation"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_629") // Require <b>admin activation</b> of multi-language support in projects ?>
            </span>
        </label>
    </div>
    <div class="mlm-setting-option-text">
        <?=RCView::tt("multilang_630", "span", array("class" => "text-danger")) // When enabled, admins must enable multi-language support in each project. This option will not affect any projects where multi-language support is already enabled (either because it had previously been enabled explicitly by an admin or there is at least one language already set up). ?>
    </div>
    <hr>
    <div class="mlm-option">
        <label for="switch-disable-from-file" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="disable-from-file" id="switch-disable-from-file">
                <label for="switch-disable-from-file"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_658") // Disable project language initialization from a file ?>
            </span>
        </label>
    </div>
    <div class="mlm-option">
        <label for="switch-disable-from-scratch" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="disable-from-scratch" id="switch-disable-from-scratch">
                <label for="switch-disable-from-scratch"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_659") // Disable project language initialization from scratch ?>
            </span>
        </label>
    </div>
    <div class="mlm-option">
        <label for="switch-force-subscription" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="force-subscription" id="switch-force-subscription">
                <label for="switch-force-subscription"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_660") // Force subscription to system language updates ?>
            </span>
        </label>
    </div>
    <div class="mlm-option">
        <label for="switch-disable-ui-overrides" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="disable-ui-overrides" id="switch-disable-ui-overrides">
                <label for="switch-disable-ui-overrides"></label>
            </span>
            <span>
                <?=RCView::tt("multilang_661") // Disabled UI overrides in subscribed system languages ?>
            </span>
        </label>
    </div>
    <div class="mlm-setting-option-text">
        <?=RCView::tt("multilang_662") // These options control how users can add new languages to projects and whether they are allowed to make changes to user interface strings. These options can be overridden for specific projects in these projects' Multi-Language Management settings. ?>
    </div>
    <hr>
    <div class="mlm-option">
        <label for="switch-debug" class="mlm-setting-option">
            <span class="switch switch-xs switch-inline">
                <input type="checkbox" class="switch" data-mlm-config="debug" id="switch-debug">
                <label for="switch-debug"></label>
            </span>
            <?=RCView::tt("multilang_17") // Debug mode (status messages will be output to the browser console) ?>
        </label>
    </div>
</div>
<?php 

#endregion

} ?>
</div><!-- mlm-tabs -->
</div><!-- mlm-setup-container -->
<?php 
#endregion

#region Modals (HTML)

#region Add Language Modal
?>
<div class="modal fade" id="mlm-add-language-modal" tabindex="-1" aria-labelledby="mlm-add-language-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <!-- #region Header -->
            <div class="modal-header">
                <h1 class="modal-title" id="mlm-add-language-modal-title"><?=RCView::tt("multilang_19") // Add New Language ?></h1>
                <button type="button" class="btn-close" data-bs-dismiss="modal" 
                    aria-label="<?=RCView::tt_js2("global_53") // Cancel ?>"></button>
            </div>
            <!-- #endregion -->
            <!-- #region Page 1 - Source Selection -->
            <div class="modal-body" data-mlm-modal-mode="add-source">
                <div class="mb-2">
                    <b><?=RCView::tt("multilang_691") // Initialize a new language &hellip; ?></b>
                </div>
                <div class="mlm-from-group">
                    <div class="form-check">
                        <input id="mlm-add-language-modal-source-system" class="form-check-input" type="radio" name="source" value="system" <?=$has_sys_langs ? "checked" : "disabled"?>>
                        <label class="form-check-label fw-bold text-dangerrc" for="mlm-add-language-modal-source-system">
                            <?=RCView::tt("multilang_36") // from available system languages ?>
                        </label>
                    </div>
                    <div class="modal-from-system">
                        <div class="modal-mlm-system-langs">
                            <select name="syslang" class="form-select form-select-sm" <?=$has_sys_langs ? "" : "disabled"?>>
                                <?php $first = true; foreach ($sys_langs as $sys_lang): ?>
                                <option value="<?=$sys_lang["key"]?>"<?=$first ? " selected" : ""?>><?=$sys_lang["display"]?></option>
                                <?php $first = false; endforeach; ?>
                                <?=$has_sys_langs ? "" : RCView::tt("multilang_37", "option", ["selected" => null]) // There are no system languages available. ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($is_project && $has_sys_langs): ?>
                    <div class="modal-import-include">
                        <div class="form-check">
                            <input id="mlm-add-language-modal-subscribed" class="form-check-input" type="checkbox" name="subscribed" checked <?=$force_subscription ? "disabled" : ""?>>
                            <label class="form-check-label mb-0" for="mlm-add-language-modal-subscribed">
                                <?=RCView::tt("multilang_656") // Keep this language updated automatically ?>*
                            </label>
                             <div class="form-text">
                                *<?=RCView::tt("multilang_657") // Note: This item might be locked by an administrator ?>
                            </div>
                       </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if($init_from_file): ?>
                <div class="modal-mlm-import-or">&ndash; <?=RCView::tt("global_47") // or ?> &ndash;</div>
                <div class="mlm-from-group">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="source" value="file" id="mlm-add-language-modal-source-file" <?=$has_sys_langs ? "" : "checked"?>>
                        <label class="form-check-label fw-bold text-dangerrc" for="mlm-add-language-modal-source-file" id="mlm-add-language-modal-source-file-label" >
                            <?=RCView::tt("multilang_33") // from a file (JSON, CSV, or INI) ?>
                        </label>
                    </div>
                    <div class="modal-from-file">
                        <input class="form-control form-control-sm" name="file" id="mlm-add-language-modal-file" type="file" accept=".json,.csv,.ini" aria-describedby="mlm-add-language-modal-source-file-label">
                        <div class="invalid-feedback"><?=RCView::tt("multilang_35") // This is not a valid language file. ?></div>
                    </div>
                    <?php if ($is_project): ?>
                    <div class="modal-import-include">
                        <div class="my-1"><?=RCView::tt("multilang_133") // Include the following: ?></div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="include-ui" id="mlm-add-language-modal-include-ui" checked>
                            <label class="form-check-label mb-0" for="mlm-add-language-modal-include-ui">
                                <?=RCView::tt("multilang_134") // Translations of user interface items ?> 
                            </label>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="include-psi" id="mlm-add-language-modal-include-psi" checked>
                            <label class="form-check-label mb-0" for="mlm-add-language-modal-include-psi">
                                <?=RCView::tt("multilang_187") // Translations of project-specific items (fields, survey settings, ASIs, alerts, ...) ?>
                            </label>*
                            <div class="form-text">
                                *<?=RCView::tt("multilang_188") // These include: fields, survey settings, ASIs, alerts, form and event names, missing data code labels, ... ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; // $is_project ?>
                </div>
                <?php endif; // $init_from_file ?>
                <?php if($init_from_scratch): ?>
                <div class="modal-mlm-import-or">&ndash; <?=RCView::tt("global_47") // or ?> &ndash;</div>
                <div class="mlm-from-group">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="source" value="new" id="mlm-add-language-modal-source-new">
                        <label class="form-check-label fw-bold text-dangerrc" for="mlm-add-language-modal-source-new">
                            <?=RCView::tt("multilang_655") // by creating the language from scratch ?>
                        </label>
                    </div>
                </div>
                <?php endif; // $init_from_scratch ?>
            </div>
            <!-- #endregion -->
            <!-- #region Page 2 - Language Info -->
            <div class="modal-body" data-mlm-modal-mode="add-info">
                <div>
                    <label for="mlm-add-language-modal-key" class="text-dangerrc fw-bold">
                        <?=RCView::tt("multilang_21") // Language ID ?>
                    </label>
                    <input type="text" pattern="^$|^[a-zA-Z]?(?:[a-zA-Z\-]*(?:[a-zA-Z]{1,}|\d{1,})$)" class="form-control form-control-sm" id="mlm-add-language-modal-key" name="key" aria-describedby="mlm-add-language-modal-key-desc" placeholder="<?=RCView::tt_attr("multilang_22") // Enter a unique ID for this language ?>" required>
                    <div class="invalid-feedback">
                        <?=RCView::tt("multilang_23") // The language ID is invalid or, when adding a new language, may already exist. ?>
                    </div>
                    <div class="mlm-input-description" id="mlm-add-language-modal-key-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_676") // A unique identifier (case-insensitive) for this language. It is recommended to use the ISO code, such as '<b>en</b>' or '<b>en-US</b>' for English, or '<b>es</b>' for Spanish. <b>Use only letters and hyphen</b> (and optionally numbers at the end). ?>
                        <a href="<?=RCView::tt_attr("multilang_624")?>" target="_blank"><u><?=RCView::tt("multilang_205")?></u></a>
                        <a href="<?=RCView::tt_attr("multilang_626")?>" target="_blank"><u><?=RCView::tt("multilang_627")?></u></a>
                        <?php if ($mycap_enabled) { ?>
                            <br><?=RCView::tt('multilang_772')?>
                            <a id="show-mclang-link" href="javascript:;" onclick="
                                                        $('#mcapp-langs-list').show();
                                                        $(this).hide();
                                                        $('#hide-mclang-link').show();">[<u><?=RCView::tt('multilang_773')?></u>]</a>
                            <a id="hide-mclang-link" style="display: none;" href="javascript:;" onclick="
                                                        $('#mcapp-langs-list').hide();
                                                        $(this).hide();
                                                        $('#show-mclang-link').show();">[<u><?=RCView::tt('multilang_774')?></u>]</a>
                        <?php } ?>
                        <span id="mcapp-langs-list" style="display: none;"><?php print $clickableMCLangsHTML; ?></span>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="mlm-add-language-modal-display" class="fw-bold text-dangerrc">
                        <?=RCView::tt("multilang_206") // Language Display Name ?>
                    </label>
                    <input type="text" class="form-control form-control-sm" id="mlm-add-language-modal-display"  name="display" aria-describedby="mlm-add-language-modal-display-desc" placeholder="<?=RCView::tt_attr("multilang_26") // Enter a display name ?>" required>
                    <div class="invalid-feedback">
                        <?=RCView::tt("multilang_27") // You must provide a value. ?>
                    </div>
                    <div class="mlm-input-description" id="mlm-add-language-modal-display-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_28") // This is the name of the language as shown in the language selectors. This should be entered in its language, such as 'English' or 'Deutsch' (for German). ?>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="mlm-add-language-modal-notes" class="fw-bold">
                        <?=RCView::tt("multilang_779") // Language Notes ?>
                    </label>
                    <textarea class="form-control form-control-sm textarea-autosize" id="mlm-add-language-modal-notes" name="notes" rows="1" style="max-height:350px;" aria-describedby="mlm-add-language-modal-notes-desc" placeholder="<?=RCView::tt_attr("multilang_780") // Enter any notes regarding this language ?>"></textarea>
                    <div class="mlm-input-description" id="mlm-add-language-modal-notes-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_781") // Notes may be useful to ... ?>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="mlm-add-language-modal-sort">
                        <?=RCView::tt("multilang_29") // Sort Override ?>
                    </label>
                    <input type="text" class="form-control form-control-sm" id="mlm-add-language-modal-sort" name="sort" aria-describedby="mlm-add-language-modal-sort-desc" placeholder="<?=RCView::tt_attr("multilang_30") // Provide a sort name (optional) ?>">
                    <div class="mlm-input-description" id="mlm-add-language-modal-sort-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_31") // If set, this will be used instead of the display name for determining the sort order of languages in language selectors. ?>
                    </div>
                </div>
            </div>
            <!-- #endregion -->
            <!-- #region Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal"><?=RCView::tt("global_53") // Cancel ?></button>
                <button type="button" class="btn btn-sm btn-primary" data-mlm-modal-btn="continue" data-mlm-modal-mode="add-source">
                    <i class="fa-solid fa-angle-right"></i> <?=RCView::tt("multilang_690") // Continue ?>
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-mlm-modal-btn="add" data-mlm-modal-mode="add-info">
                    <i class="fa-solid fa-plus"></i> <?=RCView::tt("multilang_43") // Add Language ?>
                </button>
            </div>
            <!-- #endregion -->
        </div>
    </div>
</div>
<?php
#endregion

#region Edit Language Modal
?>
<div class="modal fade" id="mlm-edit-language-modal" tabindex="-1" aria-labelledby="mlm-edit-language-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" data-mlm-mode="<?=$is_project ? "project" : "system"?>">
            <!-- #region Header -->
            <div class="modal-header mlm-original-lang-info">
                <div>
                    <h1 class="modal-title" id="mlm-edit-language-modal-title">
                        <?=RCView::tt("multilang_20") // Edit Language ?>
                        <span class="modal-language-key">
                            <span data-item="orig-id">ID</span>:
                            <span data-item="orig-name">Name</span>
                        </span>
                    </h1>
                    <div class="mt-2 fs12 mlm-show-when-subscribed">
                        <i class="fa-solid fa-bolt-lightning text-warning"></i> <?=RCView::tt("multilang_668") // Automatically updated UI translations ?>
                    </div>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=RCView::tt_js2("global_53") // Cancel ?>"></button>
            </div>
            <!-- #endregion -->
            <!-- #region Body -->
            <div class="modal-body">
                <div>
                    <label for="mlm-edit-language-modal-key" class="text-dangerrc fw-bold">
                        <?=RCView::tt("multilang_21") // Language ID ?>
                    </label>
                    <input type="text" pattern="^$|^[a-zA-Z]?(?:[a-zA-Z\-]*(?:[a-zA-Z]{1,}|\d{1,})$)" class="form-control form-control-sm" id="mlm-edit-language-modal-key" name="key" aria-describedby="mlm-edit-language-modal-key-desc" placeholder="<?=RCView::tt_attr("multilang_22") // Enter a unique ID for this language ?>" required>
                    <div class="invalid-feedback">
                        <?=RCView::tt("multilang_23") // The language ID is invalid or, when adding a new language, may already exist. ?>
                    </div>
                    <div class="mlm-input-description" id="mlm-edit-language-modal-key-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_676") // A unique identifier (case-insensitive) for this language. It is recommended to use the ISO code, such as '<b>en</b>' or '<b>en-US</b>' for English, or '<b>es</b>' for Spanish. <b>Use only letters and hyphen</b> (and optionally numbers at the end). ?>
                        <a href="<?=RCView::tt_attr("multilang_624")?>" target="_blank"><u><?=RCView::tt("multilang_205")?></u></a>
                        <a href="<?=RCView::tt_attr("multilang_626")?>" target="_blank"><u><?=RCView::tt("multilang_627")?></u></a>
                        <?php if ($mycap_enabled) { ?>
                            <br><?=RCView::tt('multilang_772')?>
                            <a id="show-mclang-link-edit" href="javascript:;" onclick="
                                                        $('#mcapp-langs-list-edit').show();
                                                        $(this).hide();
                                                        $('#hide-mclang-link-edit').show();">[<u><?=RCView::tt('multilang_773')?></u>]</a>
                            <a id="hide-mclang-link-edit" style="display: none;" href="javascript:;" onclick="
                                                        $('#mcapp-langs-list-edit').hide();
                                                        $(this).hide();
                                                        $('#show-mclang-link-edit').show();">[<u><?=RCView::tt('multilang_774')?></u>]</a>
                        <?php } ?>
                        <span id="mcapp-langs-list-edit" style="display: none;"><?php print $clickableMCLangsHTML; ?></span>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="mlm-edit-language-modal-display" class="fw-bold text-dangerrc">
                        <?=RCView::tt("multilang_206") // Language Display Name ?>
                    </label>
                    <input type="text" class="form-control form-control-sm" id="mlm-edit-language-modal-display"  name="display" aria-describedby="mlm-edit-language-modal-display-desc" placeholder="<?=RCView::tt_attr("multilang_26") // Enter a display name ?>" required>
                    <div class="invalid-feedback">
                        <?=RCView::tt("multilang_27") // You must provide a value. ?>
                    </div>
                    <div class="mlm-input-description" id="mlm-edit-language-modal-display-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_28") // This is the name of the language as shown in the language selectors. This should be entered in its language, such as 'English' or 'Deutsch' (for German). ?>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="mlm-edit-language-modal-notes" class="fw-bold">
                        <?=RCView::tt("multilang_779") // Language Notes ?>
                    </label>
                    <textarea class="form-control form-control-sm textarea-autosize" id="mlm-edit-language-modal-notes" name="notes" rows="1" style="max-height:350px;" aria-describedby="mlm-edit-language-modal-notes-desc" placeholder="<?=RCView::tt_attr("multilang_780") // Enter any notes regarding this language ?>"></textarea>
                    <div class="mlm-input-description" id="mlm-edit-language-modal-notes-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_781") // Notes may be useful to ... ?>
                    </div>
                </div>
                <div class="mt-3">
                    <label for="mlm-edit-language-modal-sort">
                        <?=RCView::tt("multilang_29") // Sort Override ?>
                    </label>
                    <input type="text" class="form-control form-control-sm" id="mlm-edit-language-modal-sort" name="sort" aria-describedby="mlm-edit-language-modal-sort-desc" placeholder="<?=RCView::tt_attr("multilang_30") // Provide a sort name (optional) ?>">
                    <div class="mlm-input-description" id="mlm-edit-language-modal-sort-desc" class="form-text text-muted">
                        <?=RCView::tt("multilang_31") // If set, this will be used instead of the display name for determining the sort order of languages in language selectors. ?>
                    </div>
                </div>
                <?php if ($is_project && $has_sys_langs): ?>
                <div class="mt-3 mb-1 mlm-show-when-based-on-syslang">
                    <div class="mb-2">
                        <span class="fw-bold text-dangerrc"><?=RCView::tt("multilang_674") // Subscription Status ?></span>
                        &ndash;
                        <?=RCView::tt("multilang_675", "i") // System Language: ?>
                        <span class="fw-bold" data-mlm-item="subscribed-to-lang">Placeholder</span>
                    </div>
                    <div class="form-check ml-1">
                        <input id="mlm-edit-language-modal-subscribed" class="form-check-input" type="checkbox" name="subscribed" checked <?=$force_subscription ? "disabled" : ""?>>
                        <label class="form-check-label mb-0" for="mlm-edit-language-modal-subscribed">
                            <?=RCView::tt("multilang_656") // Keep this language updated automatically ?>*
                        </label>
                        <div class="form-text">
                            *<?=RCView::tt("multilang_657") // Note: This item might be locked by an administrator ?>
                        </div>
                    </div>
                </div>
                <?php endif; // $is_project && $has_sys_langs ?>
            </div>
            <!-- #endregion -->
            <!-- #region Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    <?=RCView::tt("global_53") // Cancel ?>
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-mlm-modal-btn="save">
                    <i class="fa-solid fa-floppy-disk me-1"></i>
                    <?=RCView::tt("multilang_44") // Apply Changes ?>
                </button>
            </div>
            <!-- #endregion -->
        </div>
    </div>
</div>
<?php
#endregion

#region Update Language Modal
?>
<div class="modal fade" id="mlm-update-language-modal" tabindex="-1" aria-labelledby="mlm-update-language-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content" data-mlm-mode="<?=$is_project ? "project" : "system"?>">
            <!-- #region Header -->
            <div class="modal-header mlm-original-lang-info">
                <div>
                    <h1 class="modal-title" id="mlm-update-language-modal-title">
                        <?=RCView::tt("multilang_683") // Update Language ?>
                        <span class="modal-language-key">
                            <span data-item="orig-id">ID</span>:
                            <span data-item="orig-name">Name</span>
                        </span>
                    </h1>
                    <?php if ($is_project && $has_sys_langs): ?>
                    <div class="mlm-show-when-based-on-syslang fs12">
                        <div class="mt-2 mlm-show-when-subscribed">
                            <i class="fa-solid fa-bolt-lightning text-warning"></i> <?=RCView::tt("multilang_668") // Automatically updated UI translations ?>
                        </div>
                        <div class="mt-1">
                            <i class="fa-solid fa-globe"></i>
                            <?=RCView::tt("multilang_675", "i") // System Language: ?>
                            <span class="fw-bold" data-mlm-item="subscribed-to-lang">Placeholder</span>
                        </div>
                        <div class="mt-1">
                            <i class="fa-solid fa-circle-info text-secondary"></i> <?=RCView::tt("multilang_684") // Use the Edit Language dialog to change the subscription status. ?>
                        </div>
                    </div>
                    <?php endif; // $is_project && $has_sys_langs ?>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="<?=RCView::tt_js2("global_53") // Cancel ?>"></button>
            </div>
            <!-- #endregion -->
            <!-- #region Body -->
            <div class="modal-body">
                <div class="mb-2">
                    <b><?=RCView::tt("multilang_669") // Update this language &hellip; ?></b>
                </div>
                <!-- #region From System Language -->
                <?php if ($is_project): ?>
                <div class="mlm-from-group">
                    <div class="form-check">
                        <input id="mlm-update-language-modal-source-system" class="form-check-input" type="radio" name="source" value="system" <?=$has_sys_langs ? "checked" : "disabled"?>>
                        <label class="form-check-label fw-bold text-dangerrc" for="mlm-update-language-modal-source-system">
                            <?=RCView::tt("multilang_671") // by synching with a system language ?>
                        </label>
                    </div>
                    <div class="modal-from-system">
                        <div class="modal-mlm-system-langs">
                            <select name="syslang" class="form-select form-select-sm" <?=$has_sys_langs ? "" : "disabled"?>>
                                <?php foreach ($sys_langs as $sys_lang): ?>
                                <option value="<?=$sys_lang["key"]?>"><?=$sys_lang["display"]?></option>
                                <?php endforeach; ?>
                                <?=$has_sys_langs ? "" : RCView::tt("multilang_37", "option", ["selected" => null]) // There are no system languages available. ?>
                            </select>
                        </div>
                    </div>
                    <?php if ($is_project && $has_sys_langs): ?>
                    <div class="modal-import-include mlm-hide-when-based-on-syslang">
                        <div class="form-check">
                            <input id="mlm-update-language-modal-subscribed" class="form-check-input" type="checkbox" name="associate-with-syslang">
                            <label class="form-check-label mb-0" for="mlm-update-language-modal-subscribed">
                                <?=RCView::tt("multilang_685") // Additionally, associate this language with this system language ?>*
                            </label>
                             <div class="form-text">
                                *<?=RCView::tt("multilang_686", "span", [ "class" => "text-danger" ]) // Note: Once associated with a system language, this cannot be undone. ?>
                            </div>
                             <div class="form-text">
                                *<?=RCView::tt("multilang_684") // Use the <i>Edit Language</i> dialog to change the subscription status. ?>
                            </div>
                       </div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php endif; // $is_project ?>
                <!-- #endregion -->
                <!-- #region From File -->
                <div class="modal-mlm-import-or show-in-project-context">
                    &ndash; <?=RCView::tt("global_47") // or ?> &ndash;
                </div>
                <div class="mlm-from-group">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="source" value="file" id="mlm-update-language-modal-source-file" <?=$is_project && $has_sys_langs ? "" : "checked"?>>
                        <label class="form-check-label fw-bold text-dangerrc" for="mlm-update-language-modal-source-file" id="mlm-update-language-modal-source-file-label" >
                            <?=RCView::tt("multilang_672") // by importing from a file (JSON, CSV, or INI) ?>
                        </label>
                    </div>
                    <div class="modal-from-file">
                        <input class="form-control form-control-sm" name="file" id="mlm-update-language-modal-file" type="file" accept=".json,.csv,.ini" aria-describedby="mlm-update-language-modal-source-file-label">
                        <div class="invalid-feedback"><?=RCView::tt("multilang_35") // This is not a valid language file. ?></div>
                    </div>
                    <?php if ($is_project): ?>
                    <div class="modal-import-include">
                        <div class="my-1"><?=RCView::tt("multilang_133") // Include the following: ?></div>
                        <div class="form-check mb-1">
                            <input class="form-check-input" type="checkbox" name="include-ui" id="mlm-update-language-modal-include-ui" checked>
                            <label class="form-check-label mb-0" for="mlm-update-language-modal-include-ui">
                                <?=RCView::tt("multilang_134") // Translations of user interface items ?> 
                            </label>*
                            <div class="form-text">
                                *<?=RCView::tt("multilang_657") // Note: This item might be locked by an administrator ?>
                            </div>
                        </div>
                        <div class="form-check mb-0">
                            <input class="form-check-input" type="checkbox" name="include-psi" id="mlm-update-language-modal-include-psi" checked>
                            <label class="form-check-label mb-0" for="mlm-update-language-modal-include-psi">
                                <?=RCView::tt("multilang_187") // Translations of project-specific items (fields, survey settings, ASIs, alerts, ...) ?> 
                            </label>*
                            <div class="form-text">
                                *<?=RCView::tt("multilang_188") // These include: fields, survey settings, ASIs, alerts, form and event names, missing data code labels, ... ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; // $is_project ?>
                </div>
                <!-- #endregion -->
                <!-- #region Import Options -->
                <div class="mt-2 mb-2">
                    <b><?=RCView::tt("multilang_38") //Import options: ?></b>
                </div>
                <div class="modal-import-options">
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="import-merge-mode" value="keep" id="mlm-update-language-modal-keep-existing" checked>
                        <label class="form-check-label" for="mlm-update-language-modal-keep-existing">
                            <?=RCView::tt("multilang_679") // Keep existing translations ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="import-merge-mode" value="overwrite" id="mlm-update-language-modal-overwrite-existing">
                        <label class="form-check-label" for="mlm-update-language-modal-overwrite-existing">
                            <?=RCView::tt("multilang_680") // Overwrite existing translations ?>
                        </label>
                    </div>
                    <div class="form-check ml-4">
                        <input class="form-check-input" type="checkbox" name="import-empty" id="mlm-update-language-modal-overwrite-with-empty">
                        <label class="form-check-label" for="mlm-update-language-modal-overwrite-with-empty">
                            <i class="fa-solid fa-triangle-exclamation text-danger"></i>
                            <?=RCView::tt("multilang_681") // Allow blank values to overwrite (clear) existing translations ?>
                        </label>
                    </div>
                </div>
                <!-- #endregion -->
            </div>
            <!-- #endregion -->
            <!-- #region Footer -->
            <div class="modal-footer">
                <button type="button" class="btn btn-sm btn-secondary" data-bs-dismiss="modal">
                    <?=RCView::tt("global_53") // Cancel ?>
                </button>
                <button type="button" class="btn btn-primary btn-sm" data-mlm-modal-btn="update">
                    <i class="fa-solid fa-circle-chevron-up me-1"></i>
                    <?=RCView::tt("global_125") // Update ?>
                </button>
            </div>
            <!-- #endregion -->
        </div>
    </div>
</div>
<?php
#endregion

#region Import General Settings Modal
?>
<div class="modal" id="mlm-import-general-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-import-general-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header" id="mlm-import-general-title">
                <h1><?=RCView::tt("multilang_608") // Import General Settings ?></h1>
            </div>
            <div class="modal-body">
                <form class="mlm-import-general-settings-form">
                    <div class="custom-file">
                        <input type="file" class="form-control form-control-sm" name="mlm-import-file" accept=".json" />
                        <div class="invalid-feedback"><?=RCView::tt("multilang_615") // This is not a valid MLM settings file. It must be a .json file.?></div>
                    </div>
                    <div class="ms-1 mt-2 mb-2"><?=RCView::tt("multilang_133") // Include the following: ?></div>
                    <div class="form-check form-inline">
                        <input class="form-check-input" type="checkbox" name="gs-import-include-langs-tab" id="gs-import-include-langs-tab" checked>
                        <label class="form-check-label" for="gs-import-include-langs-tab">
                            <?=RCView::tt("multilang_616") // Languages tab settings ?> 
                        </label>
                    </div>
                    <div class="form-check form-inline">
                        <input class="form-check-input" type="checkbox" name="gs-import-include-forms-tab" id="gs-import-include-forms-tab" checked>
                        <label class="form-check-label" for="gs-import-include-forms-tab">
                            <?=RCView::tt("multilang_617") // Forms/Surveys tab settings ?> 
                        </label>
                    </div>
                    <div class="form-check form-inline">
                        <input class="form-check-input" type="checkbox" name="gs-import-include-alerts-tab" id="gs-import-include-alerts-tab" checked>
                        <label class="form-check-label" for="gs-import-include-alerts-tab">
                            <?=RCView::tt("multilang_618") // Alerts tab settings ?> 
                        </label>
                    </div>
                    <div class="form-check form-inline">
                        <input class="form-check-input" type="checkbox" name="gs-import-include-settings-tab" id="gs-import-include-settings-tab" checked>
                        <label class="form-check-label" for="gs-import-include-settings-tab">
                            <?=RCView::tt("multilang_619") // Settings tab settings ?> 
                        </label>
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" data-mlm-action="cancel" class="btn btn-secondary btn-sm"><?=RCView::tt("global_53") // Cancel ?></button>
                <button type="button" data-mlm-action="import" class="btn btn-primary btn-sm"><i class="fas fa-file-import"></i> <span class="mlm-modal-save-label"><?=RCView::tt_attr("global_72") // Import ?></span></button>
            </div>
        </div>
    </div>
</div>
<?php
#endregion

#region Delete Language Modal
?>
<div class="modal" id="mlm-delete-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-delete-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h1 id="mlm-delete-modal-title">
                    <?=RCView::tt("multilang_45") // Delete Language? ?>
                    <span class="modal-language-key">??</span>
                </h1>
            </div>
            <div class="modal-body">
                <?=$is_project 
                    ? RCView::tt("multilang_46") // When a language is deleted, all associated instrument/survey and user interface translations will be deleted from this project permanently. This cannot be undone. Are you sure you want to delete this language? 
                    : RCView::tt("multilang_47") // When a language is deleted, all associated user interface translations will be deleted permanently. This cannot be undone. Projects currently using this language are not affected. Are you sure you want to delete this language?
                ?>
            </div>
            <div class="modal-footer">
                <button action="cancel" type="button" class="btn btn-secondary btn-sm"><?=RCView::tt("global_53") // Cancel ?></button>
                <button action="delete" type="button" class="btn btn-danger btn-sm"><i class="far fa-trash-alt"></i> &nbsp; <?=RCView::tt("multilang_48") // Delete Language ?></button>
            </div>
        </div>
    </div>
</div>
<?php
#endregion

#region Delete Snapshot Modal
?>
<div class="modal" id="mlm-delete-snapshot-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-delete-snapshot-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h1 id="mlm-delete-snapshot-modal-title">
                    <?=RCView::tt("multilang_171") // Delete Snapshot? ?>
                </h1>
            </div>
            <div class="modal-body">
                <?=RCView::tt("multilang_172") // Are you sure you want to delete this snapshot?<br><br><b>This action cannot be undone!</b> ?>
            </div>
            <div class="modal-footer">
                <button action="cancel" type="button" class="btn btn-secondary btn-sm"><?=RCView::tt("global_53") // Cancel ?></button>
                <button action="delete" type="button" class="btn btn-danger btn-sm"><i class="far fa-trash-alt"></i> &nbsp; <?=RCView::tt("multilang_173") // Delete Snapshot ?></button>
            </div>
        </div>
    </div>
</div>
<?php
#endregion

#region Export Language Modal
?>
<div class="modal mlm-export-modal" id="mlm-export-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-export-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h1 id="mlm-export-modal-title">
                    <span class="mlm-hide-when-exporting-general">
                        <?=RCView::tt("multilang_97") // Export Language ?>
                        <span class="modal-language-key">??</span>
                    </span>
                    <span class="mlm-show-when-exporting-general">
                        <?=RCView::tt("multilang_607") // Export General Settings ?>
                    </span>
                </h1>
            </div>
            <div class="modal-body">
                <?php if ($is_project): ?>
                <div class="mlm-hide-when-exporting-changes mlm-hide-when-exporting-general">
                    <p>
                        <?=RCView::tt("multilang_98") // Set export options and download the translations of data entry and survey elements. The exported translations will reflect the current state, including potentially unsaved changes. ?>
                    </p>
                    <p class="mt-1"><?=RCView::tt("multilang_133") // Include the following: ?></p>
                    <div class="modal-export-options-checkboxes mlm-export-items form-group">
                        <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-ui" id="export-include-ui" checked>
                            <label class="form-check-label" for="export-include-ui">
                                <?=RCView::tt("multilang_134") // Translations of user interface items ?> 
                            </label>
                        </div>
                        <!-- <div class="form-check form-inline mlm-single-form-export-item">
                            <input class="form-check-input" type="checkbox" name="export-include-forms" id="export-include-forms" checked>
                            <label class="form-check-label" for="export-include-forms">
                                <?=RCView::tt("multilang_182") // Translations of instruments names ?> 
                            </label>
                        </div> -->
                        <div class="form-check form-inline mlm-single-form-export-item">
                            <input class="form-check-input" type="checkbox" name="export-include-fields" id="export-include-fields" checked>
                            <label class="form-check-label" for="export-include-fields">
                                <?=RCView::tt("multilang_135") // Translations of field items ?> 
                            </label>
                        </div>
                        <div class="form-check form-inline mlm-single-form-export-item">
                            <input class="form-check-input" type="checkbox" name="export-include-surveysettings" id="export-include-surveysettings" checked>
                            <label class="form-check-label" for="export-include-surveysettings">
                                <?=RCView::tt("multilang_575") // Translations of survey settings ?> 
                            </label>
                        </div>
                        <div class="form-check form-inline mlm-single-form-export-item">
                            <input class="form-check-input" type="checkbox" name="export-include-asis" id="export-include-asis" checked>
                            <label class="form-check-label" for="export-include-asis">
                                <?=RCView::tt("multilang_180") // Translations of automated survey invitations ?> 
                            </label>
                        </div>
                        <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-surveyqueue" id="export-include-surveyqueue" checked>
                            <label class="form-check-label" for="export-include-surveyqueue">
                                <?=RCView::tt("multilang_777") // Translations of survey queue items ?> 
                            </label>
                        </div>
                        <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-alerts" id="export-include-alerts" checked>
                            <label class="form-check-label" for="export-include-alerts">
                                <?=RCView::tt("multilang_181") // Translations of alerts ?> 
                            </label>
                        </div>
                        <!-- <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-events" id="export-include-events" checked>
                            <label class="form-check-label" for="export-include-events">
                                <?=RCView::tt("multilang_183") // Translations of event names ?> 
                            </label>
                        </div> -->
                        <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-mdc" id="export-include-mdc" checked>
                            <label class="form-check-label" for="export-include-mdc">
                                <?=RCView::tt("multilang_184") // Translations of missing data code labels ?> 
                            </label>
                        </div>
                        <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-pdf" id="export-include-pdf" checked>
                            <label class="form-check-label" for="export-include-pdf">
                                <?=RCView::tt("multilang_214") // Translations of PDF customizations ?> 
                            </label>
                        </div>
                        <div class="form-check form-inline">
                            <input class="form-check-input" type="checkbox" name="export-include-protemail" id="export-include-protemail" checked>
                            <label class="form-check-label" for="export-include-protemail">
                                <?=RCView::tt("multilang_215") // Translations of Protected Email settings ?> 
                            </label>
                        </div>
                        <?php if ($mycap_enabled): ?>
                        <div class="form-check form-inline mlm-single-form-export-item">
                            <input class="form-check-input" type="checkbox" name="export-include-mycap" id="export-include-mycap" checked>
                            <label class="form-check-label" for="export-include-mycap">
                                <?=RCView::tt("multilang_746") // Translations of MyCap settings ?> 
                            </label>
                        </div>
                        <?php endif; // $mycap_enabled ?>
                    </div>
                </div>
                <?php else: ?>
                <p>
                    <?=RCView::tt("multilang_130") // This will export the user interface translations for this language. Optionally, the translation prompts and the default values (from the currently active language file) can be included (e.g., when the file is not purely intended for transfer or backup purposes). ?>
                </p>
                <?php endif; ?>
                <p class="mt-1"><?=RCView::tt("multilang_131") // Export Options: ?></p>
                <div class="modal-export-options-checkboxes form-group">
                    <div class="form-check form-inline mlm-hide-when-exporting-general">
                        <input class="form-check-input mlm-disable-when-exporting-changes" type="checkbox" name="export-prompts" id="export-prompts">
                        <label class="form-check-label" for="export-prompts">
                            <?=RCView::tt("multilang_128") // Include translation prompts ?> 
                        </label>
                    </div>
                    <div class="form-check form-inline mlm-hide-when-exporting-general">
                        <input class="form-check-input mlm-disable-when-exporting-changes" type="checkbox" name="export-defaults" id="export-defaults">
                        <label class="form-check-label" for="export-defaults">
                            <?=RCView::tt("multilang_129") // Include default values ?> 
                        </label>
                    </div>
                    <div class="form-check form-inline mlm-hide-when-exporting-general">
                        <input class="form-check-input mlm-disable-when-exporting-changes" type="checkbox" name="export-notes" id="export-notes">
                        <label class="form-check-label" for="export-notes">
                            <?=RCView::tt("multilang_782") // Include language notes ?> 
                        </label>
                    </div>
                    <div class="form-check form-inline">
                        <input class="form-check-input" type="radio" name="export-format" id="export-format-json" value="json" checked>
                        <label class="form-check-label" for="export-format-json">
                            JSON
                        </label>
                    </div>
                    <div class="form-check form-inline">
                        <input class="form-check-input mlm-disable-when-exporting-general" type="radio" name="export-format" id="export-format-csv-comma" value="csv">
                        <label class="form-check-label" for="export-format-csv-comma">
                            CSV
                        </label>
                        &nbsp;&mdash;&nbsp;&nbsp;
                        <div class="form-check-inline">
                            <input class="form-check-input mlm-disable-when-exporting-general" type="radio" name="export-csv-format" id="export-csv-format-comma" value="comma" checked>
                            <label class="form-check-label" for="export-csv-format-comma">
                                <?=RCView::tt("global_162") // Comma (,) ?>
                            </label>
                        </div>
                        <div class="form-check-inline">
                            <input class="form-check-input mlm-disable-when-exporting-general" type="radio" name="export-csv-format" id="export-csv-format-semicolon" value="semicolon">
                            <label class="form-check-label" for="export-csv-format-semicolon">
                                <?=RCView::tt("global_164") // Semicolon (;) ?>
                            </label>
                        </div>
                        <div class="form-check-inline">
                            <input class="form-check-input mlm-disable-when-exporting-general" type="radio" name="export-csv-format" id="export-csv-format-tab" value="tab">
                            <label class="form-check-label" for="export-csv-format-tab">
                                <?=RCView::tt("global_163") // Tab ?>
                            </label>
                        </div>
                    </div>
                </div>
                <p class="small mlm-hide-when-exporting-changes"><?=RCView::tt("multilang_132") // NOTE: The exported translations will reflect the current state, including potentially unsaved changes.?></p>
            </div>
            <div class="modal-footer">
                <button action="cancel" type="button" class="btn btn-secondary btn-sm"><?=RCView::tt("global_53") // Cancel ?></button>
                <button action="download" type="button" class="btn btn-success btn-sm"><i class="fas fa-file-download"></i> &nbsp; <?=RCView::tt("api_46") // Download ?></button>
            </div>
        </div>
    </div>
</div>
<?php
#endregion

#region Rich Text Editor Modal
?>
<div class="modal" id="mlm-rte-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-rte-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h1 id="mlm-rte-modal-title">RTE</h1>
            </div>
            <div class="modal-body">
                <textarea id="mlm-rte-editor"></textarea>
            </div>
            <div class="modal-footer">
                <!-- Links -->
                <div class="modal-footer-links"><a id="mlm-modal-rte-pasteref" href="javascript:;" class="" action="paste-ref"><i class="fas fa-paste"></i></i> <?=RCView::tt("multilang_106") // Paste reference ?></a></div>
                <!-- Buttons -->
                <button action="cancel" type="button" class="btn btn-secondary btn-sm"><?=RCView::tt("global_53") // Cancel ?></button>
                <button action="apply" type="button" class="btn btn-success btn-sm"><i class="fas fa-save"></i> &nbsp; <?=RCView::tt("report_builder_28") // Save Changes ?></button>
            </div>
        </div>
    </div>
</div>
<?php
#endregion

#region Single Item Translation Modal
?>
<div class="modal" id="mlm-sit-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-sit-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h1 id="mlm-sit-modal-title">Single Item Translation</h1>
            </div>
            <div class="modal-body mlm-sit-editor-container">
                <textarea id="mlm-sit-rte"></textarea>
                <input id="mlm-sit-input" class="form-control form-control-sm" type="text">
            </div>
            <div class="modal-body mlm-sit-reference-container">
                <b>Reference:</b>
                <p class="mlm-sit-reference">Reference Value</p>
            </div>
            <div class="modal-footer">
                <!-- Links -->
                <div class="modal-footer-links"><a id="mlm-modal-sit-pasteref" href="javascript:;" class="" action="paste-ref"><i class="fas fa-paste"></i></i> <?=RCView::tt("multilang_106") // Paste reference ?></a></div>
                <!-- Buttons -->
                <button action="cancel" type="button" class="btn btn-secondary btn-sm"><?=RCView::tt("global_53") // Cancel ?></button>
                <button action="apply" type="button" class="btn btn-success btn-sm"><i class="fas fa-save"></i> &nbsp; <?=RCView::tt("report_builder_28") // Save Changes ?></button>
            </div>
        </div>
    </div>
</div>
<?php
#endregion

#region Data Changed Report Modal
?>
<div class="modal" id="mlm-dcr-modal" tabindex="-1" role="dialog" aria-labelledby="mlm-dcr-modal-title" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl" role="document">
        <div class="modal-content">
            <div class="modal-header mlm-dcr-title">
                <h1 id="mlm-dcr-modal-title"><i class="fas fa-exclamation-triangle text-danger"></i> <?=RCView::tt("multilang_566")?></h1>
            </div>
            <div class="modal-body mlm-dcr-container">
                <p class="mlm-dcr-intro yellow"><?=RCView::tt("multilang_567")?></p>
                <table class="table table-md" style="width:100%">
                    <thead>
                        <tr>
                            <th><?=RCView::tt("multilang_568") // Item ?></th>
                            <th><?=RCView::tt("multilang_569") // Default text ?></th>
                            <th><?=RCView::tt("multilang_91") // Translation ?></th>
                            <th><?=RCView::tt("docs_45") // Action ?></th>
                        </tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
            <div class="modal-footer">
                <!-- Links -->
                <div class="modal-footer-links">
                    <button data-mlm-action="accept-all-changed-items" type="button" class="btn btn-link btn-sm"><?=RCView::tt("multilang_571") // Accept all translations as valid ?></button>
                </div>
                <!-- Buttons -->
                <button data-bs-dismiss="modal" type="button" class="btn btn-secondary btn-sm"><?=RCView::tt("design_401") // Okay ?></button>
            </div>
        </div>
    </div>
    <template data-mlm-template="dcr-row-title">
        <tr class="mlm-lang-title" data-mlm-lang>
            <td colspan="3">
                <i data-mlm-display="lang" class="text-danger"></i>
            </td>
            <td>
                <a href="javascript:;" class="text-primary" data-mlm-action="export-changed-items">
                    <i class="fas fa-file-download"></i>
                    <?=RCView::tt("global_71") // Export ?>
                </a>
            </td>
        </tr>
    </template>
    <template data-mlm-template="dcr-row-item">
        <tr class="mlm-changed-item" data-mlm-lang data-mlm-type data-mlm-name data-mlm-index>
            <td data-mlm-prompt></td>
            <td data-mlm-display="default"></td>
            <td data-mlm-display="translation"></td>
            <td data-mlm-actions>
                <a href="javascript:;" class="text-primary" data-mlm-action="accept-changed-item"><?=RCView::tt("multilang_570") // Accept ?></a>&nbsp;|&nbsp;<a href="javascript:;" class="text-primary" data-mlm-action="edit-changed-item"><?=RCView::tt("global_27") // Edit ?></a>
            </td>
        </tr>
    </template>
</div>

<?php
#endregion

#endregion

#region Toasts (HTML)
?>
<!-- Success toast -->
<div class="position-fixed bottom-0 right-0 p-3" style="z-index: 99999; right: 0; bottom: 0;">
    <div id="mlm-successToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000" data-animation="true" data-autohide="true">
        <div class="toast-header">
            <svg class="bd-placeholder-img rounded me-2" width="20" height="20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMidYMid slice" focusable="false"><rect width="100%" height="100%" fill="#28a745"></rect></svg>
            <strong class="mr-auto"><?=RCView::tt("multilang_100") // Success ?></strong>
            <button type="button" class="ms-2 mb-1 close" data-bs-dismiss="toast" aria-label="<?=RCView::tt_attr("calendar_popup_01") // Close ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body" data-content="toast"></div>
    </div>
</div>
<!-- Error toast -->
<div class="position-fixed bottom-0 right-0 p-3" style="z-index: 99999; right: 0; bottom: 0;">
    <div id="mlm-errorToast" class="toast hide" role="alert" aria-live="assertive" aria-atomic="true" data-delay="2000" data-animation="true" data-autohide="false">
        <div class="toast-header">
            <svg class="bd-placeholder-img rounded me-2" width="20" height="20" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" preserveAspectRatio="xMidYMid slice" focusable="false"><rect width="100%" height="100%" fill="#dc3545"></rect></svg>
            <strong class="mr-auto"><?=RCView::tt("global_01") // ERROR ?></strong>
            <button type="button" class="ms-2 mb-1 close" data-bs-dismiss="toast" aria-label="<?=RCView::tt_attr("calendar_popup_01") // Close ?>">
                <span aria-hidden="true">&times;</span>
            </button>
        </div>
        <div class="toast-body" data-content="toast"></div>
    </div>
</div>
<?php
#endregion

} // if (!$concurrent_user)

// JS initialization
loadJS("MultiLanguage.js");
print "<script>
            window.REDCap.MultiLanguage.init($init_json);
       </script>";

print RCView::simpleDialog(RCView::tt('multilang_775') . '<br><br>' . RCView::tt('multilang_772') . $allowedLangsHTML, RCView::tt('mycap_mobile_app_101'), 'myCapLangsDialog');