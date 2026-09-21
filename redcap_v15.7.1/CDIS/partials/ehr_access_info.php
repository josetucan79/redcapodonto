<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;
use Vanderbilt\REDCap\Classes\Fhir\TokenManager\Selectors\Rules\RulesManager;

    define("NOAUTH", true);
    if(!defined('REDCAP_VERSION')) {
        require_once dirname(__DIR__, 2) . "/Config/init_global.php";
    }
    $userInfo = User::getUserInfo($userid);
    $ui_id = intval($userInfo['ui_id'] ?? false);
?>
<div data-ehr-legend>
    <div class="d-flex flex-column gap-2">
        <div>
            <i class="fas fa-circle-check fa-fw text-success"></i>
            <span><?= Language::tt('cdis_info_ehr_access_active') ?></span>
        </div>
        <div>
            <i class="fas fa-times-circle fa-fw text-danger"></i>
            <span><?= Language::tt('cdis_info_ehr_access_inactive_02') ?></span>
        </div>
        <div>
            <i class="fas fa-ban fa-fw text-danger"></i>
            <span><?= Language::tt('cdis_info_ehr_access_forbidden') ?></span>
            <?php if(FhirEhr::canAccessTokenPriorityRules($Proj, $ui_id)) : ?>
            <a href="<?= RulesManager::getFormURL($project_id) ?>">
                <i class="fas fa-arrow-up-right-from-square fa-fw text-secondary"></i>
            </a>
            <?php endif; ?>
        </div>
    </div>
    
    <details class="mt-2">
        <summary><span class="fw-bold"><?= Language::tt('cdis_info_ehr_access_info_summary') ?></span></summary>
        <p><?= Language::tt('cdis_info_ehr_access_info_description') ?></p>
    </details>
    
    <details class="mt-2">
        <summary><span class="fw-bold"><?= Language::tt('cdis_info_ehr_access_get_access_summary_02') ?></span></summary>
        <p><?= Language::tt('cdis_info_ehr_access_get_access_description') ?></p>
        <ul>
            <li><?= Language::tt('cdis_info_ehr_access_get_access_method_1') ?></li>
            <li><?= Language::tt('cdis_info_ehr_access_get_access_method_2') ?></li>
        </ul>
        <div class="alert alert-info"><?= Language::tt('cdis_info_ehr_access_get_access_note') ?></div>
    </details>
    
    <details class="mt-2">
        <summary><span class="fw-bold"><?= Language::tt('cdis_info_ehr_access_usage_summary') ?></span></summary>
        <p><?= Language::tt('cdis_info_ehr_access_usage_description_1') ?></p>
        <p><?= Language::tt('cdis_info_ehr_access_usage_description_2') ?></p>
        <ul>
            <li><?= Language::tt('cdis_info_ehr_access_usage_criteria_1') ?></li>
            <ul>
                <li><?= Language::tt('cdis_info_ehr_access_usage_criteria_2') ?></li>
                <li><?= Language::tt('cdis_info_ehr_access_usage_criteria_3') ?></li>
                <li><?= Language::tt('cdis_info_ehr_access_usage_criteria_4') ?></li>
            </ul>
        </ul>
    </details>
    <details class="mt-2">
        <summary><span class="fw-bold"><?= Language::tt('cdis_token_priority_rules_short_description_title') ?></span></summary>
        <p><?= Language::tt('cdis_token_priority_rules_short_description_text') ?></p>
        <?php if(FhirEhr::canAccessTokenPriorityRules($Proj, $ui_id)) : ?>
        <span class="d-block">
            <a href="<?= RulesManager::getFormURL($project_id) ?>">
                <?= Language::tt('cdis_token_priority_rules_short_description_link') ?>
                <i class="fas fa-arrow-up-right-from-square fa-fw text-secondary"></i>
            </a>
        </span>
        <?php endif; ?>
    </details>
</div>
<style>
[data-ehr-legend] details {
    border-radius: 5px;
    border: solid 1px #cacaca;
    padding: 10px;
}
</style>