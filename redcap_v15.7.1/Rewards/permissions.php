<?php include __DIR__.'/partials/header.php'; ?>
<?php

use Vanderbilt\REDCap\Classes\Rewards\Facades\EntityManager;
use Vanderbilt\REDCap\Classes\Utility\SessionDataUtils;

use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as Gate;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\UserPermissionEntity;

// If user does not have Project Setup/Design rights, do not show this page
// if (!$user_rights['design']) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
if(Gate::denies(PermissionEntity::MANAGE_PERMISSIONS)) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
// if(!ACCESS_SYSTEM_CONFIG) redirect(APP_PATH_WEBROOT."index.php?pid=$project_id");
include __DIR__.'/partials/tabs.php';

$entityManager = EntityManager::get();
$userRepo = $entityManager->getRepository(UserEntity::class);
$userPermissionRepo = $entityManager->getRepository(UserPermissionEntity::class);
$permissionRepo = $entityManager->getRepository(PermissionEntity::class);


if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $permissions = $_POST['permissions'] ?? [];
        $reason = $_POST['reason'] ?? false;

        if(!$reason) throw new Exception("A reson must be provided", 401);

        $reason = strip_tags($reason);
    
        foreach ($permissions as $userID => $perms) {

            $userEntity = $entityManager->getReference(UserEntity::class, $userID);

            foreach ($perms as $permID => $value) {

                $permissionEntity = $entityManager->getReference(PermissionEntity::class, $permID);

                // $value will be 'true' or 'false' as a string
                $isGranted = ($value === 'true');

                // Check if this permission already exists
                $existingPermission = $userPermissionRepo->findOneBy([
                    'user' => $userEntity,
                    'permission' => $permissionEntity,
                    'project_id' => $project_id
                ]);
                
                if ($isGranted) {
                    // Create or update permission
                    if (!$existingPermission) {
                        $userPermissionEntity = new UserPermissionEntity();
                        $userPermissionEntity->setUser($userEntity);
                        $userPermissionEntity->setPermission($permissionEntity);
                        $userPermissionEntity->setProjectId($project_id);
                        $entityManager->persist($userPermissionEntity);
                    }
                } else if ($existingPermission) {
                    // Remove permission if it exists
                    $entityManager->remove($existingPermission);
                }
            }
        }

        // Flush all changes at once
        $entityManager->flush();

        Logging::logEvent(
            $sql = '',
            $table = 'redcap_rewards_user_permissions',
            $event = 'MANAGE',
            $identifier = PROJECT_ID,
            $display = json_encode($permissions, JSON_PRETTY_PRINT),
            $descrip = 'Updated user permissions',
            $change_reason = $reason
        );
        flash('alert-success', 'Permissions saved!');
    } catch (\Throwable $th) {
        flash('alert-danger', $th->getMessage());
    } finally {
        redirect(previousURL());
    }
}
?>

<?php
/**
 * @var Generator|PermissionEntity[] $allPermissions
 */
$allPermissions = $permissionRepo->findAllExcept([
    PermissionEntity::VIEW_LOGS,
    PermissionEntity::VIEW_ORDERS
]);

function getPermissionlabel(PermissionEntity $permission) {
    $label = $permission->getName();
    $convertString = function($input) {
        // Convert the string to lowercase
        $lowercaseString = strtolower($input);
        // Replace underscores with spaces
        $convertedString = str_replace('_', ' ', $lowercaseString);
        return $convertedString;
    };
    return $convertString($label);
}

function getPermissionDescription(PermissionEntity $permission) {
    $name = $permission->getName();
    $map = [
        PermissionEntity::REVIEW_ELIGIBILITY => 'Grants the user the ability to approve or reject participant rewards. This permission is typically assigned to the role of a <strong>Reviewer</strong>. Users with this permission can assess whether participants meet the criteria for rewards and make the final decision on reward approval.',
        PermissionEntity::MANAGE_API_SETTINGS => 'Grants the user the ability to modify the settings required to connect to the rewards provider’s API. Users with this permission can configure the Client ID, Client Secret, as well as the Group and Campaign Identifiers.',
        PermissionEntity::MANAGE_PERMISSIONS => 'Provides the user with the ability to manage user permissions. This includes assigning, modifying, or revoking permissions for other users. It is a critical role that is generally reserved for administrators to control who can access and perform certain actions within the system.',
        PermissionEntity::MANAGE_PROJECT_SETTINGS => 'Allows the user to manage various settings related to the Participant Compensation system. This includes customizing reward notification emails, defining how participant information is displayed, and configuring other project-specific settings. Users with this permission can ensure that all settings are properly configured and aligned with the goals of the project.',
        PermissionEntity::MANAGE_REWARD_OPTIONS => 'Allows the user to manage the reward options available within the system. This permission is typically assigned to the role of a <strong>Rewards Options Manager</strong>. Users with this permission can add new rewards, update existing ones, or remove rewards that are no longer valid. This ensures that the reward options are current and aligned with the goals of the project.',
        PermissionEntity::PLACE_ORDERS => 'Grants the user the ability to place orders for approved rewards. This permission is typically assigned to the role of a <strong>Buyer</strong>. Users with this permission can initiate the final step in the reward process, ensuring that participants receive their redeemable codes or gift cards.',
    ];
    $description = $map[$name] ?? '';
    return $description;
}

$allUsernames = array_keys(UserRights::getPrivileges($project_id)[$project_id] ?? []);
$allUsers = array_map(function($username) {
    return User::getUserInfo($username);
}, $allUsernames);

?>
<div style="max-width: 800px; clear: both">
    
    <p>In this page administrators can control and manage the specific actions that users can perform within the Participant Compensation system. By assigning or revoking permissions, you can define roles and responsibilities, ensuring that users have access only to the features and actions relevant to their roles. This granular control helps maintain the security and integrity of the system.</p>
    <form action="" method="POST" class="border rounded p-2" id="permissions-form">
        <table class="table table-bordered table-striped table-hover">
            <thead>
                <tr>
                    <th><?= $lang['global_17'] ?></th>
                    <?php foreach ($allPermissions as $permission): ?>
                        <th>
                            <span><?= $label = getPermissionlabel($permission) ?></span>
                            <button type="button" class="btn btn-xs btn-transparent"
                                data-help="<?= ucwords(strtolower(getPermissionlabel($permission))) ?>"
                                data-help-description="<?= getPermissionDescription($permission) ?>"
                            >
                                <i class="fas fa-circle-question"></i>
                            </button>
                        </th>
                    <?php endforeach; ?>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allUsers as $user):
                    $userID = $user['ui_id'] ?? null;
                    $hasPermission = $userRepo->useHasPermission($project_id, $userID);
                ?>
                <tr>
                    <td><?= $user['username'] ?></td>
                    
                    <?php
                    /** @var PermissionEntity $permission */
                    foreach ($allPermissions as $permission): ?>
                        <td data-permission-name="<?= $permission->getName() ?>">
                            <input type="hidden" name="permissions[<?=$user['ui_id'] ?>][<?= $permission->getId() ?>]" value="false">
                            <input type="checkbox" name="permissions[<?=$user['ui_id'] ?>][<?= $permission->getId() ?>]" value="true" <?= $hasPermission($permission) ? 'checked' : '' ?>>
                        </td>
                    <?php endforeach; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php include(__DIR__.'/partials/save_buttons.php') ?>
    </form>
    <?= SessionDataUtils::getAlerts() ?>
    <hr>
    <?php include __DIR__.'/partials/permissions_legend.php'; ?>


</div>

<template id="permissions-changed-dialog-template">
    <span class="fw-bold d-block fs-6 mb-2">This change will be logged for auditing purposes.</span>
    <header>If you are changing permissions for <strong>placing orders</strong>, please remember that this will grant the user the ability to place orders for approved rewards. This permission is typically assigned to the role of a <code>Buyer</code>. Users with this permission can initiate the final step in the reward process, ensuring that participants receive their redeemable codes or gift cards.</header>
    <div class="alert alert-warning mt-2 mb-0">
        <div>
            <span class="d-block fw-bold my-2">Please provide a reason for this change:</span>
            <input class="form-control form-control-sm" type="text" placeholder="Provide a reason..."/>
        </div>
        <div class="form-check my-2">
            <input class="form-check-input" type="checkbox" id="acknowledgmentCheckbox" />
            <label class="form-check-label" for="acknowledgmentCheckbox">
                I acknowledge that this action will be recorded for auditing purposes.
            </label>
        </div>
    </div>
</template>

<script type="module">
    import {useModal} from '<?= APP_PATH_JS.'Composables/index.es.js.php' ?>'

    const modal = useModal();
    const helpButtons = document.querySelectorAll('[data-help]');

    helpButtons.forEach(element => {
        const title = element.getAttribute('data-help');
        const description = element.getAttribute('data-help-description');
        element.addEventListener('click', (e) => {
            modal.show({
                title: title,
                body: description,
                cancelText: null,
            });
        })
    });
</script>
<script type="module">
    import { useFormModal } from './assets/PermissionsUtils.js'

    useFormModal("#permissions-form")

</script>
<?php
// Footer
include APP_PATH_DOCROOT . 'ProjectGeneral/footer.php';
