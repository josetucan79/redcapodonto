<?php

use Vanderbilt\REDCap\Classes\Fhir\FhirEhr;

require_once dirname(__FILE__, 3) . '/Config/init_project.php';

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

$projectOwner = intval($Proj->project['created_by'] ?? null);
$currentUserId = intval(UI_ID);

$canView = FhirEhr::canAccessTokenPriorityRules($Proj, $currentUserId);
if(!$canView) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");

include APP_PATH_DOCROOT . 'ProjectGeneral/header.php';

$lang['priority_rules'] = 'Access Token Priority Rules';
?>
<?= sprintf('<div class="projhdr"><i class="fas fa-arrow-down-1-9"></i> %s</div>', $lang['priority_rules']) ?>
<style>
    @import url('./assets/style.css');
</style>
<script type="module">
    import {useModal, useToaster, EventBus, useAssetLoader} from '<?= APP_PATH_JS.'Composables/index.es.js.php' ?>'
    import App from './assets/App.js'
    
    const modal = useModal() // generic modal for alerts
    const formModal = useModal('#rule-form-modal')
    const toaster = useToaster()

    const app = new App({
        formModal,
        modal,
        toaster,
    })
    app.run()
</script>

<div style="max-width: 800px;">
    <?php include(__DIR__.'/partials/description.php'); ?>

    <div class="border rounded p-2 d-flex flex-column gap-2 mt-2">
        <div id="rule-list">
            <div>
                <span class="fw-bold d-block"><?= Language::tt('cdis_token_priority_rules_list_title') ?></span>
                <span>
                    <?= Language::tt('cdis_token_priority_rules_list_description') ?>
                </span>
            </div>
            <table class="table table-bordered table-striped table-hover m-0">
                <thead>
                    <tr>
                        <th><?= Language::tt('cdis_token_priority_table_header_priority') ?></th>
                        <th><?= Language::tt('cdis_token_priority_table_header_user') ?></th>
                        <th><?= Language::tt('cdis_token_priority_table_header_allow_disallow') ?></th>
                        <th>
                            <div class="d-flex justify-content-between">
                                <span><?= Language::tt('cdis_token_priority_table_header_actions') ?></span>
                                <button id="add-rule-button" class="btn btn-xs btn-success" type="submit">
                                    <i class="fas fa-plus fa-fw"></i>
                                    <span><?= Language::tt('cdis_token_priority_button_add_rule') ?></span>
                                </button>
                            </div>
                        </th>
                    </tr>
                </thead>
                <tbody id="rule-table-body">
                    <!-- Rules will be dynamically populated here -->
                </tbody>
            </table>
            <span class="d-block small fst-italic text-muted"><?= Language::tt('token_priority_order_description') ?></span>
        </div>
    
        <div id="global-toggle">
            
            <div>
                <span class="fw-bold d-block">
                    <?= Language::tt('cdis_token_priority_global_rule_title') ?>
                </span>
                <span>
                    <?= Language::tt('cdis_token_priority_global_rule_description') ?>
                </span>
            </div>

                <!-- Radio for Allow All -->
            <div>
                <input 
                type="radio" 
                name="globalRule" 
                id="global-rule-allow" 
                value="allow"
                />
                <label class="m-0" for="global-rule-allow">
                    <strong><?= Language::tt('cdis_token_priority_global_rule_allow_all_title') ?></strong>
                </label>
                <span class="d-block small fst-italic text-muted explanation">
                    <?= Language::tt('cdis_token_priority_global_rule_allow_all_description') ?>
                </span>
            </div>

                <!-- Radio for Disallow All -->
            <div>
                <input 
                type="radio" 
                name="globalRule" 
                id="global-rule-disallow" 
                value="disallow" 
                />
                <label class="m-0" for="global-rule-disallow">
                    <strong><?= Language::tt('cdis_token_priority_global_rule_disallow_all_title') ?></strong>
                </label>
                <span class="d-block small fst-italic text-muted explanation">
                    <?= Language::tt('cdis_token_priority_global_rule_disallow_all_description') ?>
                </span>
            </div>
        </div>
        
        <div class="d-flex justify-content-end mt-2 border-top py-2">
            <button id="button-save" type="button" class="btn btn-primary btn-sm">
                <i class="fas fa-save fa-fw"></i>
                <span>
                    <?= Language::tt('cdis_token_priority_button_save') ?>
                </span>
            </button>
        </div>
    </div>
</div>


<template id="rule-form-modal">
  <div data-header>
    <span data-title>New rule</span>
    <button type="button" data-btn-close aria-label="Close"></button>
  </div>
  <div data-body>
    <div class="d-flex align-items-center gap-2">
        <label class="d-inline-block form-label m-0" for="user-select">User:</label>
        <select class="form-control form-control-sm" id="user-select">
            <option value="" disabled>Please select</option>
        </select>
        <div class="d-flex gap-2 align-items-center">
            <input class="form-check-input" type="checkbox" id="allow-toggle" />
            <label class="form-check-label m-0" for="allow-toggle">Allow</label>
        </div>
    </div>
  </div>
  <div data-footer>
    <button type="button" data-btn-cancel>Cancel</button>
    <button type="button" data-btn-ok>Ok</button>
  </div>
</template>

