<?php

require_once dirname(dirname(__FILE__)) . "/Config/init_project.php";

// Save new or existing popup
if ($isAjax && isset($_POST['popup_id']) && isinteger($_POST['popup_id'])) {
    echo json_encode(\DescriptivePopup::save($_POST));
    exit;
}

// Header
include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';
// Tabs
include APP_PATH_DOCROOT . "ProjectSetup/tabs.php";
// CSS/JS
loadCSS('descriptive_popups.css');
loadJS('DescriptivePopups.js');
addLangToJS(['descriptive_popups_36','design_397','global_53','design_654','descriptive_popups_37','report_builder_28']);

$linkTextAllPopups = \DescriptivePopup::getLinkTextAllPopups();
$currentViewSettings = null;
if (isset($_GET['popid']) && !empty(\DescriptivePopup::getPopupSettings($_GET['popid']))) {
    $currentViewSettings = \DescriptivePopup::getPopupSettings($_GET['popid']);
} else {
    if (!isset($_GET['add_new']) && !isset($_GET['view_only'])) {
        \DescriptivePopup::renderPopupsTable();
        exit();
    }
}
if ($currentViewSettings == null) {
    $title = RCView::tt("descriptive_popups_32");
}
else {
    $title = RCView::lang_i("descriptive_popups_33", [RCView::span(["class"=>"text-dangerrc"], strip_tags($currentViewSettings['inline_text']))], false);
}
?>
    <div id="descriptivePopupsEditor" style="max-width:800px;">
        <div class="mt-2 mb-4">
            <button class="btn btn-sm btn-defaultrc fs13" style="color:#00529f;background-color:#f5f5f5;" onclick="window.location.href=app_path_webroot+'Design/descriptive_popups.php?pid='+pid"><i class="fa-solid fa-circle-chevron-left mr-1"></i><?=RCView::tt('descriptive_popups_21')?></button>
        </div>
        <h1><?= $title ?></h1>
        <form id="descriptivePopupForm" style="margin-top:15px;">
            <!-- Link Text -->
            <div>
                <b><?=RCView::tt("descriptive_popups_10")?></b> - <?=RCView::tt("descriptive_popups_22")?>
            </div>
            <div id="linkTextRequired" class="alert alert-danger" style="display:none; margin-top: 10px;">
                <?=RCView::tt("descriptive_popups_25")?> <span class="close-icon" onclick="dismissErrorMssg(this)" style="cursor: pointer;">&times;</span>
            </div>
            <input type="text" name="inline_text" style="margin-bottom:15px;" class="form-control mt-1" value="<?= $currentViewSettings ? htmlspecialchars($currentViewSettings['inline_text']) : '' ?>">
            <!-- Popup Text -->
            <div class="mb-2">
                <b><?=RCView::tt("descriptive_popups_23")?></b> - <?=RCView::tt("descriptive_popups_24")?>
            </div>
            <div id="popupDescriptionRequired" class="alert alert-danger mt-2" style="display:none;">
                <?=RCView::tt("descriptive_popups_26")?> <span class="close-icon" onclick="dismissErrorMssg(this)" style="cursor: pointer;">&times;</span>
            </div>
            <textarea name="inline_text_popup_description" class="descriptive_popup_text form-control">
                <?= $currentViewSettings ? htmlspecialchars($currentViewSettings['inline_text_popup_description'], ENT_NOQUOTES) : '' ?>
            </textarea>

            <!-- Main Display Options -->
            <h2 class="pt-2 text-dangerrc">
                <?=RCView::tt("descriptive_popups_19")?>
            </h2>
            <!-- Link Color -->
            <div class="mt-3">
                <label><b><?= $lang['descriptive_popups_07'] ?></b> - <?= $lang['descriptive_popups_31'] ?></label>
                <input type="color" name="hex_link_color" class="form-control color-picker ml-2" style="width:50px;display:inline-block;cursor:pointer;" value="<?= $currentViewSettings ? htmlspecialchars($currentViewSettings['hex_link_color']) : '#3E72A8' ?>">
            </div>
            <!-- Where to display -->
            <div class="d-flex justify-content-start mt-2">
                <div class="form-group mr-3">
                    <input type="checkbox" name="active_on_data_entry_forms" id="active_on_data_entry_forms" <?= ($currentViewSettings === null || ($currentViewSettings && $currentViewSettings['active_on_data_entry_forms'])) ? 'checked' : '' ?>>
                    <label for="active_on_data_entry_forms" class="ml-1"><b><?= $lang['descriptive_popups_14'] ?></b></label>
                </div>
                <div class="form-group mr-3">
                    <input type="checkbox" name="active_on_surveys" id="active_on_surveys" <?= $currentViewSettings && $currentViewSettings['active_on_surveys'] ? 'checked' : '' ?>>
                    <label for="active_on_surveys" class="ml-1"><b><?= $lang['descriptive_popups_13'] ?></b></label>
                </div>
                <div class="form-group">
                    <input type="checkbox" name="first_occurrence_only" id="first_occurrence_only" <?= $currentViewSettings && $currentViewSettings['first_occurrence_only'] ? 'checked' : '' ?>>
                    <label for="first_occurrence_only" class="ml-1">
                        <b><?=RCView::tt("descriptive_popups_15")?></b>
                    </label>
                </div>
            </div>

            <!-- Additional Display Options -->
            <h2 class="text-dangerrc">
                <?=RCView::tt("descriptive_popups_34")?>
            </h2>
            <!-- Forms and Page Numbers (for surveys) -->
            <div class="mt-3 mb-1">
                <b><?=RCView::tt("descriptive_popups_35")?></b> - <?=RCView::tt("descriptive_popups_28")?>
            </div>
            <select id="formsSelect" name="list_instruments" class="form-control" multiple onchange="updatePageNumbersTextArea();">
                <?php
                foreach ($Proj->forms as $formName => $attr): ?>
                    <option value="<?= $formName ?>" <?= $currentViewSettings && in_array($formName, $currentViewSettings['list_instruments']) ? 'selected' : '' ?>>
                        <?= $attr['menu'] ?? $formName ?>
                    </option>
                <?php endforeach; ?>
            </select>
            <div id="pageNumbersWrapper" class="mb-3">
                <b><?=RCView::tt("descriptive_popups_20")?></b> - 
                <?=RCView::lang_i("descriptive_popups_29", ["<code>form_1:1,2,3</code>"], false)?>
                <textarea id="pageNumbers" name="list_survey_pages" class="form-control textarea-resizable mt-2" rows="3">
                    <?= !empty($currentViewSettings['list_survey_pages']) 
                        ? implode("\n", array_map(function($form, $pages) { 
                            return htmlspecialchars($Proj->forms[$form]['menu'] ?? $form) . ': ' . implode(', ', $pages); }, array_keys($currentViewSettings['list_survey_pages']), $currentViewSettings['list_survey_pages'])) 
                        : '' 
                    ?>
                </textarea>
            </div>
            <button type="button" class="btn btn-sm btn-primaryrc" data-action="save-popup">
                <?=RCView::tt("descriptive_popups_30") // Save?>
            </button>
        </form>
    </div>

    </body>
<?php

// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';