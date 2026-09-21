<?php namespace ExternalModules; ?>

<tr data-module='<?= ExternalModules::escape($prefix) ?>' data-version='<?= ExternalModules::escape($version) ?>'>
    <td>
        <?php require __DIR__ . '/../templates/module-table.php'; ?>
    </td>
    <td class="external-modules-action-buttons">
        <?php
            if (ExternalModules::userCanEnableDisableModule($prefix)) {
                ?><button class='enable-button'>Enable</button><?php
            }
            elseif ($GLOBALS['external_modules_allow_activation_user_request']) {
                $requestPending = \ToDoList::isExternalModuleRequestPending($prefix, ExternalModules::getProjectId());
                $requestPendingDisabled = $requestPending ? "disabled" : "";
                ?><button class='enable-button module-request' <?=$requestPendingDisabled?>>Request Activation</button><?php
                if ($requestPending) {
                    ?><div class='text-danger'>Activation request is pending</div><?php
                }
            }
        ?>
    </td>
</tr>