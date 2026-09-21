<?php include __DIR__.'/partials/header.php'; ?>
<?php

use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\Facades\ProjectSettings;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\RewardOptionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Providers\Tango\Entities\TangoProjectSettingsVO;
use Vanderbilt\REDCap\Classes\Rewards\Utility\SmartVarialblesUtility;

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
if(Gate::denies(PermissionEntity::MANAGE_PROJECT_SETTINGS)) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
// if(!ACCESS_SYSTEM_CONFIG) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
include __DIR__.'/partials/tabs.php';

$smartVariables = [
    SmartVarialblesUtility::VARIABLE_AMOUNT => 'reward-amount',
    SmartVarialblesUtility::VARIABLE_PRODUCT => 'reward-product-id',
    SmartVarialblesUtility::VARIABLE_PRODUCT_NAME => 'reward-product-name',
    SmartVarialblesUtility::VARIABLE_STATUS => 'reward-status',
    SmartVarialblesUtility::VARIABLE_REDCAP_ORDER => 'reward-redcap-order-id',
    SmartVarialblesUtility::VARIABLE_PROVIDER_ORDER => 'reward-provider-order-id',
    SmartVarialblesUtility::VARIABLE_LINK => 'reward-link',
    SmartVarialblesUtility::VARIABLE_URL => 'reward-url',
];

/** @var TangoProjectSettingsVO */
$settings = ProjectSettings::get($project_id);

include __DIR__.'/lib/UIHelper.php';
UIHelper::checkSettings($settings);

$entityManager = EntityManager::get();

$rewardOptionsRepository = $entityManager->getRepository(RewardOptionEntity::class);
$rewardOptions = $rewardOptionsRepository->findActiveByProjectId($project_id);

// Create reward options dropdown HTML
function renderRewardOptionsDropdown($rewardOptions) {
    ob_start();
    ?>
    <select class="reward-options form-select btn-xs pe-5" >
        <option value="">Select reward option</option>
        <?php foreach ($rewardOptions as $rewardOption) : ?>
        <option value="<?= $id = $rewardOption->getId() ?>"><?= $id ?></option>
        <?php endforeach; ?>
    </select>
    <?php
    return ob_get_clean();
}

// Create smart variables dropdown HTML with dynamic target
function renderSmartVariablesDropdown($smartVariables, $targetId) {
    ob_start();
    ?>
    <div class="dropdown smart-variables">
        <button class="btn btn-secondary btn-xs dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
            Insert Smart Variable
        </button>

        <ul class="dropdown-menu">
            <?php foreach ($smartVariables as $key => $label): ?>
            <li><a class="dropdown-item" href="#" data-target="#<?= $targetId ?>" data-variable="<?= $key ?>"><?= $label ?></a></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php
    return ob_get_clean();
}

$emailTemplate = $settings->getEmailTemplate();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $settings->setEmailTemplate($_POST[TangoProjectSettingsVO::KEY_EMAIL_TEMPLATE] ?? null);
        $settings->setEmailSubject($_POST[TangoProjectSettingsVO::KEY_EMAIL_SUBJECT] ?? null);
        $settings->setEmailFrom($_POST[TangoProjectSettingsVO::KEY_EMAIL_FROM] ?? null);
        $settings->setPreviewExpression($_POST[TangoProjectSettingsVO::KEY_PREVIEW_EXPRESSION] ?? null);
        $settings->setParticipantDetails($_POST[TangoProjectSettingsVO::KEY_PARTICIPANT_DETAILS] ?? null);
        ProjectSettings::save($project_id, $settings);

        flash('alert-success', 'Settings saved!');
    } catch (\Throwable $th) {
        flash('alert-danger', $th->getMessage());
    } finally {
        redirect(previousURL());
    }
}
?>

<div style="max-width: 800px; clear: both">
    <div>
        <p><?= Language::tt('rewards_project_settings_intro') ?></p>
        <form class="reward-form" action="" method="POST">
            <div>
                <div class="d-flex justify-content-between">
                    <details>
                        <summary>
                            <span class="form-label" for="tango-preview"><?= Language::tt('custom_reward_label_label') ?></span>
                        </summary>
                        <span class="field-description"><?= Language::tt('custom_reward_label_description') ?></span>
                    </details>
                    <template data-preview-target="#tango-preview"></template>
                </div>
                <input class="form-control form-control-sm" type="text" name="<?= TangoProjectSettingsVO::KEY_PREVIEW_EXPRESSION?>" id="tango-preview" value="<?= $settings->getPreviewExpression() ?>">
                <span class="field-detail"><?= Language::tt('custom_reward_label_example') ?></span>
            </div>
            <div>
                <div class="d-flex justify-content-between">
                    <details>
                        <summary>
                            <span class="form-label" for="tango-participant-details"><?= Language::tt('participant_details_label') ?></span>
                        </summary>
                        <span class="field-description"><?= Language::tt('participant_details_description') ?></span>
                    </details>
                    <template data-preview-target="#tango-participant-details"></template>
                </div>
                <textarea class="form-control form-control-sm" type="text" name="<?= TangoProjectSettingsVO::KEY_PARTICIPANT_DETAILS?>" id="tango-participant-details" rows="3"><?= $settings->getParticipantDetails() ?></textarea>
                <span class="field-detail"><?= Language::tt('participant_details_example') ?></span>
            </div>
            <div>
                <details>
                    <summary>
                        <span class="form-label" for="tango-email-from"><?= Language::tt('rewards_from_email_label') ?></span>
                    </summary>
                    <span class="field-description"><?= Language::tt('rewards_from_email_description') ?></span>
                </details>
                <input class="form-control form-control-sm" type="text" name="<?= TangoProjectSettingsVO::KEY_EMAIL_FROM?>" id="tango-email-from" value="<?= $settings->getEmailFrom() ?>">
            </div>
            <div>
                <div class="d-flex justify-content-between">
                    <details>
                        <summary>
                            <span class="form-label" for="tango-email-subject"><?= Language::tt('rewards_email_subject_label') ?></span>
                        </summary>
                        <span class="field-description"><?= Language::tt('rewards_email_subject_description') ?></span>
                    </details>
                    <div class="d-flex gap-2">
                        <div class="btn-group dropdown-group">
                            <?= renderRewardOptionsDropdown($rewardOptions) ?>
                            <?= renderSmartVariablesDropdown($smartVariables, 'tango-email-subject') ?>
                        </div>
                        <template data-preview-target="#tango-email-subject"></template>
                    </div>
                </div>
                <input class="form-control form-control-sm" type="text" name="<?= TangoProjectSettingsVO::KEY_EMAIL_SUBJECT?>" id="tango-email-subject" value="<?= $settings->getEmailSubject() ?>">
            </div>
            <div class="mb-2">
                <div class="d-flex justify-content-between">
                    <details>
                        <summary>
                            <span class="form-label" for="tango-email-template"><?= Language::tt('rewards_email_template_label') ?></span>
                        </summary>
                        <span class="field-description">
                            <span><?= Language::tt('rewards_email_template_description') ?></span>
                            <ul>
                                <li><code>[reward-link]</code> <?= Language::tt('rewards_email_template_variables_1') ?></li>
                                <li><code>[reward-url]</code> <?= Language::tt('rewards_email_template_variables_2') ?></li>
                            </ul>
                    </details>
                    <div class="d-flex gap-2">
                        <div class="btn-group dropdown-group">
                            <?= renderRewardOptionsDropdown($rewardOptions) ?>
                            <?= renderSmartVariablesDropdown($smartVariables, 'tango-email-template') ?>
                        </div>
                        <template data-preview-target="#tango-email-template"></template>
                    </div>
                </div>

                <textarea id="tango-email-template" class="x-form-field notesbox mceEditor" name="<?= TangoProjectSettingsVO::KEY_EMAIL_TEMPLATE ?>" style="height:250px;"><?= $settings->getEmailTemplate() ?></textarea>
            </div>
            <?php include(__DIR__.'/partials/save_buttons.php') ?>
        </form>
    </div>
    <?= SessionDataUtils::getAlerts(); ?>
</div>
<script type="module">
    import { insertText, saveCaretPosition } from '<?= APP_PATH_JS ?>Composables/index.es.js.php'

    import { usePreviewHandler } from './assets/PreviewManager.js'
    
    window.initTinyMCEglobal('mceEditor', false)

    const previewManager = usePreviewHandler();
    previewManager.init();

    // Get all dropdown groups
    document.querySelectorAll('.dropdown-group').forEach(group => {
        const rewardOptionDropdown = group.querySelector('.reward-options');
        const smartVariablesDropdown = group.querySelector('.smart-variables.dropdown');
        const smartVariablesDropdownButton = smartVariablesDropdown.querySelector('button.dropdown-toggle');
        const smartVariableMenuItems = smartVariablesDropdown.querySelectorAll('.dropdown-item');

        // Disable smart variable items until reward option is selected
        const updateSmartVariableState = () => {
            const rewardSelected = rewardOptionDropdown.value.trim() !== '';

            // Add/remove a "disabled" class to the dropdown wrapper (for styling/visual cue)
            smartVariablesDropdownButton.classList.toggle('disabled', !rewardSelected);

            // Optionally, also disable individual items to prevent interaction
            smartVariableMenuItems.forEach(item => {
                item.classList.toggle('disabled', !rewardSelected);
                item.setAttribute('aria-disabled', String(!rewardSelected));
            });
        };

        updateSmartVariableState();

        // Attach click handler to each smart variable menu item
        smartVariableMenuItems.forEach(item => {
            const targetElementSelector = item.getAttribute('data-target');
            const smartVariable = item.getAttribute('data-variable');

            // The target selector is already correctly set for each dropdown group
            const targetElement = document.querySelector(targetElementSelector);

            if (!targetElement) return;

            item.addEventListener('click', (e) => {
                e.preventDefault();
                if (item.classList.contains('disabled')) return;

                const rewardId = rewardOptionDropdown.value;
                const insertTextValue = `[${smartVariable}:R-${rewardId}]`;

                targetElement.focus();
                const lastPosition = saveCaretPosition(targetElement);
                insertText(targetElement, insertTextValue, lastPosition);
            });
        });

        rewardOptionDropdown.addEventListener('change', updateSmartVariableState);
    });

</script>
<style>
	@import url('<?= APP_PATH_WEBROOT_FULL . 'redcap_v' . REDCAP_VERSION ?>/Rewards/assets/form.css');
</style>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';