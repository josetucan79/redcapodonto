<?php

use Vanderbilt\REDCap\Classes\Rewards\ORM\Entities\PermissionEntity;
use Vanderbilt\REDCap\Classes\Rewards\Facades\PermissionsGateFacade as gate;
$urls = [
    'index' => [
        'link' => 'Rewards/index.php',
        'title' => 'Information',
        'classes' => 'fas fa-home' // 'fas fa-person-circle-check',
    ],
    'manager' => [
        'link' => 'Rewards/manager.php',
        'title' => 'Participant Manager',
        'classes' => 'fas fa-icons' // 'fas fa-person-circle-check',
    ],
    // [
    //     'link' => 'Rewards/reward_options.php',
    //     'title' => 'Reward Options',
    //     'classes' => 'fas fa-icons',
    // ],
    PermissionEntity::MANAGE_PROJECT_SETTINGS => [
        'link' => 'Rewards/settings.php',
        'title' => 'Settings',
        'classes' => 'fas fa-cog',
    ],
    // [
    //     'link' => 'Rewards/orders.php',
    //     'title' => 'Orders',
    //     'classes' => 'fas fa-folder-open',
    // ],
    // [
    //     'link' => 'Rewards/logs.php',
    //     'title' => 'Logs',
    //     'classes' => 'fas fa-file-lines',
    // ],
    PermissionEntity::MANAGE_PERMISSIONS => [
        'link' => 'Rewards/permissions.php',
        'title' => 'Permissions',
        'classes' => 'fas fa-lock',
    ],
    PermissionEntity::MANAGE_API_SETTINGS => [
        'link' => 'Rewards/api_settings.php',
        'title' => 'API Settings',
        'classes' => 'fas fa-cloud',
    ],
    /* 'configuration_check' => [
        'link' => 'Rewards/configuration_check.php',
        'title' => 'Configuration Check',
        'classes' => 'fas fa-square-check',
    ], */
];

// if(Gate::denies(PermissionEntity::REVIEW_ELIGIBILITY)) return;
// if(Gate::denies(PermissionEntity::REVIEW_ELIGIBILITY)) return;
// if(Gate::denies(PermissionEntity::PLACE_ORDERS)) return;
// if(Gate::denies(PermissionEntity::VIEW_LOGS)) return;
// if(Gate::denies(PermissionEntity::MANAGE_REWARD_OPTIONS)) return;
// if(Gate::denies(PermissionEntity::VIEW_EMAILS)) return;
// if(Gate::denies(PermissionEntity::VIEW_ORDERS)) return;
if(Gate::denies(PermissionEntity::MANAGE_PERMISSIONS)) unset($urls[PermissionEntity::MANAGE_PERMISSIONS]);
if(Gate::denies(PermissionEntity::MANAGE_PROJECT_SETTINGS)) unset($urls[PermissionEntity::MANAGE_PROJECT_SETTINGS]);
if(Gate::denies(PermissionEntity::MANAGE_API_SETTINGS)) unset($urls[PermissionEntity::MANAGE_API_SETTINGS]);
?>

    <div id="sub-nav" class="d-none d-sm-block" style="margin:5px 0 20px;">
        <ul>
        <?php foreach ($urls as $url) : ?>
            <li class="<?= ($url['link'] === PAGE) ? 'active' : '' ?>">
                <a href="<?=APP_PATH_WEBROOT.$url['link'] ?>?pid=<?= $project_id?>" style="font-size:13px;color:#393733;padding:6px 9px 7px 10px;"><i class="<?= $url['classes'] ?>"></i> <?= $url['title'] ?></a>
            </li>
        <?php endforeach; ?>
        </ul>
    </div>
    <div style="clear: both;"></div>
